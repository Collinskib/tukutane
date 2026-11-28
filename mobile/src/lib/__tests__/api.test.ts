import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { api, getToken, setToken, imgUrl, ApiError } from '../api';

jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
}));
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

const A = AsyncStorage as jest.Mocked<typeof AsyncStorage>;
const S = SecureStore as jest.Mocked<typeof SecureStore>;

beforeEach(() => {
  jest.clearAllMocks();
  S.setItemAsync.mockResolvedValue();
  S.deleteItemAsync.mockResolvedValue();
  A.setItem.mockResolvedValue(undefined);
  A.removeItem.mockResolvedValue(undefined);
  (global as any).fetch = jest.fn();
});

const okJson = (data: any, status = 200) =>
  Promise.resolve({ ok: status < 400, status, json: async () => data } as Response);

describe('token storage (native → SecureStore)', () => {
  it('reads from SecureStore first', async () => {
    S.getItemAsync.mockResolvedValue('tok123');
    expect(await getToken()).toBe('tok123');
    expect(A.getItem).not.toHaveBeenCalled();
  });

  it('migrates a legacy AsyncStorage token into SecureStore', async () => {
    S.getItemAsync.mockResolvedValue(null);
    A.getItem.mockResolvedValue('legacy-tok');
    expect(await getToken()).toBe('legacy-tok');
    expect(S.setItemAsync).toHaveBeenCalledWith('nipange.token', 'legacy-tok');
    expect(A.removeItem).toHaveBeenCalledWith('nipange.token');
  });

  it('returns null when nothing is stored', async () => {
    S.getItemAsync.mockResolvedValue(null);
    A.getItem.mockResolvedValue(null);
    expect(await getToken()).toBeNull();
  });

  it('writes and clears via SecureStore', async () => {
    await setToken('abc');
    expect(S.setItemAsync).toHaveBeenCalledWith('nipange.token', 'abc');
    await setToken(null);
    expect(S.deleteItemAsync).toHaveBeenCalledWith('nipange.token');
  });
});

describe('api()', () => {
  it('GETs with route + params, skips empty values', async () => {
    (global.fetch as jest.Mock).mockReturnValue(okJson({ items: [] }));
    S.getItemAsync.mockResolvedValue('tok');
    const out = await api('feed', { view: 'discover', q: '', page: 2, when: undefined });
    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toContain('/api/app.php?');
    expect(url).toContain('r=feed');
    expect(url).toContain('view=discover');
    expect(url).toContain('page=2');
    expect(url).not.toContain('q=');
    expect(url).not.toContain('when=');
    expect(init.method).toBe('GET');
    expect(init.headers.Authorization).toBe('Bearer tok');
    expect(out).toEqual({ items: [] });
  });

  it('POSTs JSON when a body is given', async () => {
    (global.fetch as jest.Mock).mockReturnValue(okJson({ ok: true }));
    S.getItemAsync.mockResolvedValue('tok');
    await api('rsvp', {}, { id: 5, action: 'join' });
    const [, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(init.method).toBe('POST');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(JSON.parse(init.body)).toEqual({ id: 5, action: 'join' });
  });

  it('sends no Authorization header when logged out', async () => {
    (global.fetch as jest.Mock).mockReturnValue(okJson({ items: [] }));
    S.getItemAsync.mockResolvedValue(null);
    A.getItem.mockResolvedValue(null);
    await api('feed');
    expect((global.fetch as jest.Mock).mock.calls[0][1].headers.Authorization).toBeUndefined();
  });

  it('throws ApiError with server message on {error}', async () => {
    (global.fetch as jest.Mock).mockReturnValue(okJson({ error: 'Messages are for friends.' }, 403));
    S.getItemAsync.mockResolvedValue('tok');
    await expect(api('messages', {}, { conv: 1, body: 'hi' }))
      .rejects.toMatchObject({ message: 'Messages are for friends.', status: 403 });
  });

  it('throws ApiError on non-2xx without a body message', async () => {
    (global.fetch as jest.Mock).mockReturnValue(okJson(null, 500));
    S.getItemAsync.mockResolvedValue('tok');
    await expect(api('feed')).rejects.toBeInstanceOf(ApiError);
  });

  it('maps aborts to a friendly timeout message', async () => {
    const err = new Error('aborted'); err.name = 'AbortError';
    (global.fetch as jest.Mock).mockRejectedValue(err);
    S.getItemAsync.mockResolvedValue('tok');
    await expect(api('feed')).rejects.toMatchObject({ status: 0 });
  });

  it('maps network failure to a friendly message', async () => {
    (global.fetch as jest.Mock).mockRejectedValue(new TypeError('Network request failed'));
    S.getItemAsync.mockResolvedValue('tok');
    await expect(api('feed')).rejects.toMatchObject({ status: 0 });
  });
});

describe('imgUrl()', () => {
  it('builds upload URLs and passes through empties', () => {
    expect(imgUrl('a.jpg')).toContain('/uploads/a.jpg');
    expect(imgUrl('')).toBeUndefined();
    expect(imgUrl(null)).toBeUndefined();
    expect(imgUrl(undefined)).toBeUndefined();
  });
});
