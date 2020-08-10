# Talk

Transmit sensitive data safely

## Background
How to transmit sensitive data safely via the Internet? Emails? Well, that's a bad idea. Your password for your email could be hacked and you never know whether your email service provider would take a look at your emails.
To stay safe, you must encrypt the transmitted data. GnuPG is a great tool to encrypt your message. However, it requires both the sender and receiver to have GPG client installed.


[Talk](https://github.com/zeruniverse/Talk) is inspired by the [Password-Manager](https://github.com/zeruniverse/Password-Manager) project, which stores your data encrypted with your passphrase and generates a unique link for the data. The receiver can access this link and use the same passphrase to obtain the data. Thus, instead of sending data directly, you just send this link.

The passphrase can be the answer to one question that both you and the receiver know. The question (or passphrase hint) will be shown after the receiver clicks the link.

Of course, just like [Password-Manager](https://github.com/zeruniverse/Password-Manager), data will be encrypted and decrypted at client-side to make sure the service provider won't have access to the data.

## Demo
Here is the project example: [talk.zzeyu.com](http://talk.zzeyu.com)

## Installation

You need at least PHP 7.1, MySQL support and HTTPS support on your server.

1. Copy everything in `src` folder to your server

2. Edit `js/crypto.js`, change `CLIENT_SALT` to a new random string.

3. Edit `function/config.php` with your server setup.

4. Enable HTTPS on your site

5. If you use Nginx, configure your 404 page so it redirects to `404redirect.php`. This is to enable short link