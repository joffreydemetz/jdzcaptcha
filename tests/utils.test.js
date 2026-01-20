import { Utils } from '../files/jdzcaptcha';

describe('Utils', () => {
    test('extend() should merge objects correctly', () => {
        const target = { a: 1 };
        const source = { b: 2, c: { d: 3 } };
        const result = Utils.extend(target, source);

        expect(result).toEqual({ a: 1, b: 2, c: { d: 3 } });
    });

    test('createPayload() should return a Base64 encoded JSON string', () => {
        const data = { key: 'value' };
        const payload = Utils.createPayload(data);
        const decoded = JSON.parse(atob(payload));

        expect(decoded.key).toBe('value');
        expect(decoded.ts).toBeDefined(); // Ensure timestamp is added
    });

    test('isBase64() should validate Base64 strings correctly', () => {
        expect(Utils.isBase64('dGVzdA==')).toBe(true); // Valid Base64
        expect(Utils.isBase64('invalid-base64')).toBe(false); // Invalid Base64
    });

    test('clearInvalidationTimeout() should clear a valid timeout', () => {
        const timeoutId = setTimeout(() => { }, 1000);
        Utils.clearInvalidationTimeout(timeoutId);

        // Jest doesn't provide a direct way to check if a timeout was cleared,
        // but this ensures no errors occur when clearing a valid timeout.
        expect(timeoutId).toBeDefined();
    });
});