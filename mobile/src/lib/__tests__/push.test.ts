import { routeForLink } from '../push';

jest.mock('expo-notifications', () => ({
  setNotificationHandler: jest.fn(),
  addNotificationResponseReceivedListener: jest.fn(() => ({ remove: jest.fn() })),
  getLastNotificationResponseAsync: jest.fn(async () => null),
}));
jest.mock('expo-device', () => ({ isDevice: false }));
jest.mock('expo-constants', () => ({ expoConfig: { extra: {} } }));
jest.mock('expo-router', () => ({ router: { push: jest.fn() } }));
jest.mock('../api', () => ({ api: jest.fn() }));

describe('routeForLink()', () => {
  it('maps web links to app routes', () => {
    expect(routeForLink('event.php?id=42')).toBe('/event/42');
    expect(routeForLink('messages.php?c=7')).toBe('/chat/7');
    expect(routeForLink('group.php?id=9')).toBe('/group/9');
    expect(routeForLink('venue.php?id=3')).toBe('/venue/3');
    expect(routeForLink('user.php?id=11')).toBe('/user/11');
  });

  it('handles extra query params', () => {
    expect(routeForLink('event.php?id=42&ref=push')).toBe('/event/42');
    expect(routeForLink('index.php?x=1&event.php?id=5')).toBe('/event/5');
  });

  it('returns null for unknown links', () => {
    expect(routeForLink('')).toBeNull();
    expect(routeForLink('profile.php')).toBeNull();
    expect(routeForLink('https://example.com/x')).toBeNull();
  });
});
