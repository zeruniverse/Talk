# Talk
Transmit sensitive data safely
## Background
How to transmit sensitive data safely via the Internet? Emails? Well, it's a bad idea. Your password for your email address could be cracked and you never know whether your email provider would take a look at your emails.

To stay safe, you must encrypt the data transmitted. GnuPG is a great tool to encrypt your message. However, it requires both the sender and receiver to have GPG client installed.
## Design
[Talk](https://github.com/zeruniverse/Talk) is a tool to safely transmit sensitive data, inspired by the [Password-Manager](https://github.com/zeruniverse/Password-Manager) project, which stores your data encrypted with your passphrase and generates a unique link. The receiver can access this link and use the same passphrase to obtain the data. Thus, instead of sending data directly, you just send this link to transmit data safely.

The passphrase can be the answer to one question that both you and the receiver know. The question (or passphrase hint) will be shown after the receiver clicks the link.

Just like [Password-Manager](https://github.com/zeruniverse/Password-Manager), data will be encrypted and decrypted at client-side to make sure the service provider won't have access to the data.

## Demo
Here is the project example: [talk.zzeyu.com](http://talk.zzeyu.com).
