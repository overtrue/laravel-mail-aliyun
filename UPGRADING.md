# Upgrading to 4.x

This is a breaking transport migration from SwiftMailer to Symfony Mailer for
Laravel 13 and PHP 8.3+. Support for Laravel 12 and earlier and PHP below 8.3
is removed. Applications on older Laravel versions must stay on a compatible
earlier package version.

- Replace `withSwiftMessage()` callbacks and `Swift_Mime_SimpleMessage` type hints
  with `withSymfonyMessage()` and `Symfony\Component\Mime\Email`. The `HasTagName`
  trait has been migrated for you.
- Direct calls to `send()` now follow Symfony's transport contract: pass a
  structured MIME `Message` convertible to `Email` (normally an `Email`) and an
  optional `Envelope`. Plain `RawMessage` instances are rejected; use SMTP for
  raw messages. The return value
  is `SentMessage|null`, not a recipient count. The by-reference failed-recipient
  argument is no longer supported.
- Custom subclasses must migrate from the removed Laravel transport base class.
  `doSend(SentMessage): void` replaces the old Swift send hooks. The protected
  `payload`, `getTo` and `getTagName` helpers now accept Symfony objects;
  `allContacts` and `getBodyName` are removed. Review overrides before upgrading.
- This transport rejects BCC and attachments before making an HTTP request.
  Previously BCC was silently removed and attachments were not represented by
  the API payload. Use SMTP if you need these features. Alibaba's newer API has
  limited BCC support, but this transport does not implement it across its
  supported regional API versions.
- Explicit envelope recipients now determine delivery, but envelope-only
  recipients absent from To/CC headers are rejected to avoid exposing private
  addresses through the API’s visible `ToAddress` field. CC recipients in a
  normal message remain included in `ToAddress`; the API does not preserve a
  separate CC header. Recipient display names are not sent in `ToAddress`.
- `from_address` remains the configured Aliyun account, falling back to the
  message's From address. A distinct envelope sender does not override this
  account. Empty/null sender or region settings now use message/default values.
- Text and HTML alternatives are sent in their respective API fields; zero-valued
  API options are preserved. HTTP failures are exposed as Symfony transport
  exceptions, with the underlying Guzzle exception available as their cause.

Configure `MAIL_MAILER=directmail` and add a `directmail` entry with
`transport => directmail` in `config/mail.php`; see README for the full example.
