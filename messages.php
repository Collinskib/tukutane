<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('messages.php');

$uid  = user_id();
$conv = null;
$other = null;

// ?to=N → open/create conversation with that friend
if (!empty($_GET['to'])) {
    $to = (int) $_GET['to'];
    $rel = friendship($uid, $to);
    if (!$rel || $rel['status'] !== 'accepted') {
        flash('You can only message friends.', 'err');
        redirect('user.php?id=' . $to);
    }
    if (is_blocked($uid, $to)) {
        flash('That conversation is unavailable.', 'err');
        redirect('friends.php');
    }
    $conv  = get_conversation($uid, $to);
    $other = find_user($to);
} elseif (!empty($_GET['c'])) {
    $conv = conversation_for((int) $_GET['c'], $uid);
    if ($conv) $other = find_user((int) ($conv['user_one'] === $uid ? $conv['user_two'] : $conv['user_one']));
}

$convs = my_conversations($uid);
if ($conv) mark_conversation_read((int) $conv['id'], $uid);
$messages = $conv ? messages_since((int) $conv['id'], 0, 80) : [];

page_head($other ? 'Chat with ' . $other['name'] : 'Messages');
site_header();
?>
<main>
<h2 style="margin-top:6px">Messages</h2>
<div class="chat-layout" <?php if (!$convs && !$conv) echo 'style="display:block"'; ?>>
  <?php if ($convs || $conv): ?>
  <div class="conv-list" id="convlist">
    <?php foreach ($convs as $c): ?>
    <a href="messages.php?c=<?= (int) $c['id'] ?>" class="<?= $conv && (int) $conv['id'] === (int) $c['id'] ? 'on' : '' ?>">
      <?= avatar_html(['name' => $c['other_name'], 'photo' => $c['other_photo']]) ?>
      <span class="who"><b><?= e($c['other_name']) ?></b>
        <small><?= e(mb_strimwidth((string) ($c['last_msg'] ?? 'Say hello 👋'), 0, 40, '…')) ?></small></span>
      <?php if ($c['unread']): ?><span class="unread"><?= (int) $c['unread'] ?></span><?php endif; ?>
      <?php if ($c['muted']): ?><small title="Muted">🔇</small><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($conv && $other): ?>
  <div class="thread" data-conv="<?= (int) $conv['id'] ?>">
    <div class="thread-head">
      <?= avatar_html($other) ?>
      <div class="who"><b><?= e($other['name']) ?></b></div>
      <a class="btn-mini" href="user.php?id=<?= (int) $other['id'] ?>">Profile</a>
    </div>
    <div class="msgs" id="msgs" data-last="<?= $messages ? (int) end($messages)['id'] : 0 ?>">
      <?php foreach ($messages as $m): ?>
      <div class="bubble <?= (int) $m['sender_id'] === $uid ? 'me' : 'them' ?>" data-mid="<?= (int) $m['id'] ?>">
        <?= nl2br(e($m['body'])) ?><small><?= e(date('g:i A', strtotime($m['created_at']))) ?></small>
      </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?><div class="empty" style="margin:auto"><span class="big">👋</span><p>Say hi to <?= e($other['name']) ?>!</p></div><?php endif; ?>
    </div>
    <form class="composer" id="composer" action="/api/messages.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="conv" value="<?= (int) $conv['id'] ?>">
      <input name="body" id="msgbody" autocomplete="off" maxlength="1000" placeholder="Write a message…" required>
      <button type="submit" id="sendbtn">Send</button>
    </form>
  </div>
  <?php else: ?>
  <div class="empty" style="margin:auto;min-height:300px">
    <span class="big">💬</span>
    <p>Pick a conversation, or message a friend from <a href="friends.php">Friends</a>.</p>
  </div>
  <?php endif; ?>
</div>
</main>

<?php if ($conv && $other): ?>
<script>
(function(){
  var box = document.getElementById('msgs');
  var conv = <?= (int) $conv['id'] ?>;
  var last = +box.dataset.last || 0;
  box.scrollTop = box.scrollHeight;

  function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML.replace(/\n/g,'<br>');}
  function add(m){
    var b=document.createElement('div');
    b.className='bubble '+(m.sender_id==<?= $uid ?>?'me':'them');
    b.dataset.mid=m.id;
    b.innerHTML=esc(m.body)+'<small>'+m.time+'</small>';
    b.style.opacity='0';b.style.transform='translateY(6px)';
    box.appendChild(b);
    requestAnimationFrame(function(){b.style.transition='opacity .2s ease-out,transform .2s ease-out';b.style.opacity='1';b.style.transform='none';});
    box.scrollTop=box.scrollHeight;
  }
  function poll(){
    if(document.hidden)return;
    fetch('/api/messages.php?conv='+conv+'&after='+last,{headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json()})
      .then(function(j){(j.messages||[]).forEach(function(m){if(+m.id>last){last=+m.id;add(m);}});})
      .catch(function(){});
  }
  setInterval(poll,5000);

  document.getElementById('composer').addEventListener('submit',function(ev){
    ev.preventDefault();
    var inp=document.getElementById('msgbody'),btn=document.getElementById('sendbtn');
    var body=inp.value.trim();if(!body)return;
    btn.disabled=true;
    fetch('/api/messages.php',{method:'POST',body:new FormData(this),headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json()})
      .then(function(j){
        btn.disabled=false;
        if(j.ok&&j.message){add(j.message);last=+j.message.id;inp.value='';inp.focus();}
        else if(j.error)toast(j.error,true);
      })
      .catch(function(){btn.disabled=false;toast('Could not send — try again',true);});
  });
})();
</script>
<?php endif; ?>
<?php bottom_nav('messages'); site_footer(); ?>
