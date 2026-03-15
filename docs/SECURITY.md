# Security Notes

## Session Settings
- Idle timeout: 30 minutes (`SESSION_IDLE_TIMEOUT`)
- Absolute timeout: 8 hours (`SESSION_ABSOLUTE_TIMEOUT`)
- Cookies: HttpOnly, SameSite=Lax, secure flag auto-detected by HTTPS
- Strict mode enabled to mitigate session fixation

## Authentication
- Passwords are hashed with `password_hash()`
- Minimum password length is controlled by `PASSWORD_MIN_LENGTH`
- Login rate limiting: 5 attempts per 15 minutes per IP

## Response Headers
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- Content Security Policy enforced with `style-src 'unsafe-inline'` for inline styles
