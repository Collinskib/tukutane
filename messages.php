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
    if ($conv) {
        $other = find_user((int) ($conv['user_one'] === $uid ? $conv['user_two'] : $conv['user_one']));
        if ($other && is_blocked($uid, (int) $other['id'])) {
            flash('That conversation is unavailable.', 'err');
            redirect('messages.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conv) {
    csrf_verify();
    if (in_array($_POST['do'] ?? '', ['mute', 'unmute'], true)) {
        set_conversation_muted((int) $conv['id'], $uid, $_POST['do'] === 'mute');
        flash($_POST['do'] === 'mute' ? 'Conversation muted.' : 'Conversation unmuted.');
    }
    redirect('messages.php?c=' . (int) $conv['id']);
}

$convs = my_conversations($uid);
if ($conv) mark_conversation_read((int) $conv['id'], $uid);
$messages = $conv ? messages_since((int) $conv['id'], 0, 80) : [];
$convMuted = $conv ? conversation_muted((int) $conv['id'], $uid) : false;

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
      <div class="who"><b><?= e($other['name']) ?></b><?php if ($convMuted): ?><small>🔇 muted</small><?php endif; ?></div>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="<?= $convMuted ? 'unmute' : 'mute' ?>">
        <button class="btn-mini" type="submit"><?= $convMuted ? 'Unmute' : 'Mute' ?></button></form>
      <button class="btn-mini" type="button" data-dialog="#reportdlg" id="headreport">Report</button>
      <form method="post" action="friend.php" style="display:inline"
            onsubmit="return confirm('Block <?= e($other['name']) ?>? You won\\'t see or message each other.')"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $other['id'] ?>"><input type="hidden" name="do" value="block">
        <button class="btn-mini" type="submit" title="Block">🚫</button></form>
      <a class="btn-mini" href="user.php?id=<?= (int) $other['id'] ?>">Profile</a>
    </div>
    <div class="msgs" id="msgs" data-last="<?= $messages ? (int) end($messages)['id'] : 0 ?>">
      <?php foreach ($messages as $m): ?>
      <div class="bubble <?= (int) $m['sender_id'] === $uid ? 'me' : 'them' ?>" data-mid="<?= (int) $m['id'] ?>">
        <?php if ($m['attachment'] && $m['attachment_kind'] === 'image'): ?>
        <a href="uploads/<?= e($m['attachment']) ?>" target="_blank"><img class="attach-img" src="uploads/<?= e($m['attachment']) ?>" alt="<?= e($m['attachment_name'] ?? 'image') ?>" loading="lazy"></a>
        <?php elseif ($m['attachment']): ?>
        <a class="attach-file" href="uploads/<?= e($m['attachment']) ?>" target="_blank" download="<?= e($m['attachment_name'] ?? 'file') ?>">📄 <?= e($m['attachment_name'] ?? 'attachment') ?></a>
        <?php endif; ?>
        <?= nl2br(e($m['body'])) ?><small><?= e(date('g:i A', strtotime($m['created_at']))) ?><?php if ((int) $m['sender_id'] !== $uid): ?><button class="msg-flag" type="button" data-dialog="#reportdlg" data-report-msg="<?= (int) $m['id'] ?>" title="Report this message">⚑</button><?php endif; ?></small>
      </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?><div class="empty" style="margin:auto"><span class="big">👋</span><p>Say hi to <?= e($other['name']) ?>!</p></div><?php endif; ?>
      <div class="bubble them typing-bubble" id="tind" hidden aria-label="<?= e($other['name']) ?> is typing"><span class="td"></span><span class="td"></span><span class="td"></span></div>
    </div>
    <form class="composer" id="composer" action="/api/messages.php" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="conv" value="<?= (int) $conv['id'] ?>">
      <input type="file" name="attach" id="attach" hidden
             accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.ppt,.pptx,.zip">
      <button type="button" class="icon-btn" id="attachbtn" title="Attach a photo or file" aria-label="Attach">📎</button>
      <span class="attach-chip" id="attachchip" hidden></span>
      <input name="body" id="msgbody" autocomplete="off" maxlength="1000" placeholder="Write a message…">
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
<dialog id="reportdlg">
  <form method="post" action="report.php">
    <h3>Report this message</h3>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="message">
    <input type="hidden" name="id" id="repmsgid" value="0">
    <input type="hidden" name="back" value="messages.php?c=<?= (int) $conv['id'] ?>">
    <div class="field"><label>What's wrong?</label>
      <textarea name="reason" required maxlength="300" rows="3" placeholder="Harassment, spam, unsafe…"></textarea></div>
    <div class="actions">
      <button type="button" class="btn-ghost" data-close>Cancel</button>
      <button class="btn-danger" type="submit">Send report</button>
    </div>
  </form>
</dialog>
<script>
(function(){
  var box = document.getElementById('msgs');
  var conv = <?= (int) $conv['id'] ?>;
  var last = +box.dataset.last || 0;
  box.scrollTop = box.scrollHeight;

  function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML.replace(/\n/g,'<br>');}
  function escAttr(s){return esc(s).replace(/"/g,'&quot;');}
  function attachHtml(m){
    if(!m.attachment)return '';
    if(m.attachment_kind==='image')
      return '<a href="uploads/'+m.attachment+'" target="_blank"><img class="attach-img" src="uploads/'+m.attachment+'" loading="lazy" alt="image"></a>';
    return '<a class="attach-file" href="uploads/'+m.attachment+'" target="_blank" download="'+escAttr(m.attachment_name||'file')+'">📄 '+esc(m.attachment_name||'attachment')+'</a>';
  }
  function add(m){
    var b=document.createElement('div');
    var mine = m.sender_id==<?= $uid ?>;
    b.className='bubble '+(mine?'me':'them');
    b.dataset.mid=m.id;
    b.innerHTML=attachHtml(m)+esc(m.body)+'<small>'+m.time+(mine?'':'<button class="msg-flag" type="button" data-dialog="#reportdlg" data-report-msg="'+m.id+'" title="Report this message">⚑</button>')+'</small>';
    b.style.opacity='0';b.style.transform='translateY(6px)';
    box.insertBefore(b, tind);
    requestAnimationFrame(function(){b.style.transition='opacity .2s ease-out,transform .2s ease-out';b.style.opacity='1';b.style.transform='none';});
    box.scrollTop=box.scrollHeight;
  }
  var tind = document.getElementById('tind');
  function poll(){
    if(document.hidden)return;
    fetch('/api/messages.php?conv='+conv+'&after='+last,{headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json()})
      .then(function(j){
        (j.messages||[]).forEach(function(m){if(+m.id>last){last=+m.id;add(m);}});
        var was = !tind.hidden;
        tind.hidden = !j.typing;
        if(j.typing&&!was)box.scrollTop=box.scrollHeight;
      })
      .catch(function(){});
  }
  setInterval(poll,5000);

  // typing presence: ping at most once every 2.5s while the box has text
  var _tp = 0, csrf = document.querySelector('#composer input[name=csrf]').value;
  document.getElementById('msgbody').addEventListener('input', function(){
    var now = Date.now();
    if (!this.value.trim() || now - _tp < 2500) return;
    _tp = now;
    var fd = new FormData();
    fd.append('csrf', csrf); fd.append('conv', conv); fd.append('do', 'typing');
    fetch('/api/messages.php', {method:'POST', body:fd, headers:{'X-Requested-With':'fetch'}}).catch(function(){});
  });

  // message reporting: per-bubble flags pick that message; head button picks the last incoming one
  var repInput = document.getElementById('repmsgid');
  document.addEventListener('click', function(ev){
    var flag = ev.target.closest('[data-report-msg]');
    if (flag) { repInput.value = flag.getAttribute('data-report-msg'); return; }
    if (ev.target.closest('#headreport')) {
      var thems = box.querySelectorAll('.bubble.them[data-mid]');
      if (thems.length) repInput.value = thems[thems.length-1].dataset.mid;
      else { ev.preventDefault(); ev.stopImmediatePropagation(); toast('No message from them to report yet', true); }
    }
  });

  document.getElementById('composer').addEventListener('submit',function(ev){
    ev.preventDefault();
    var inp=document.getElementById('msgbody'),btn=document.getElementById('sendbtn'),
        fileInp=document.getElementById('attach'),chip=document.getElementById('attachchip');
    var body=inp.value.trim();if(!body&&!fileInp.files.length)return;
    btn.disabled=true;
    fetch('/api/messages.php',{method:'POST',body:new FormData(this),headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json()})
      .then(function(j){
        btn.disabled=false;
        if(j.ok&&j.message){add(j.message);last=+j.message.id;inp.value='';fileInp.value='';chip.hidden=true;inp.focus();}
        else if(j.error)toast(j.error,true);
      })
      .catch(function(){btn.disabled=false;toast('Could not send — try again',true);});
  });

  // attachment picker: 📎 opens the file dialog, chip shows the pick
  var fileInp2=document.getElementById('attach'),chip2=document.getElementById('attachchip');
  document.getElementById('attachbtn').addEventListener('click',function(){fileInp2.click();});
  fileInp2.addEventListener('change',function(){
    chip2.hidden=!this.files.length;
    if(this.files.length)chip2.textContent='📎 '+this.files[0].name;
  });
})();
</script>
<?php endif; ?>
<?php bottom_nav('messages'); site_footer(); ?>
