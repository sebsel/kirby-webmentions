# Kirby Webmentions Plugin

This is my own spin on [Bastian Allgeiers](https://github.com/bastianallgeier/kirby-webmentions) Kirby plugin for sending and receiving [webmentions](http://indieweb.org/Webmention) with your Kirby site. It might have functionality you don't need, or miss functionality you want, but it does what I need it to do.

## Deprecated: unfortunately I have no time to maintain this plugin, but it works on my site. Feel free to use it, but it might need some work to fit in your site.

## Install

[Download the plugin](https://github.com/sebsel/seblog-kirby-webmentions/archive/master.zip) and upload it to your Kirby site under `/site/plugins/`. Rename the folder to `webmentions`. The file `webmentions.php` should then be located at `/site/plugins/webmentions/webmentions.php`

To enable webmentions in a template, you can use the webmentions() helper:

```php
<?php echo webmentions() ?>
```

Additionally you have to specify your new webmention endpoint in the header of your site:

```php
<link rel="webmention" href="<?php echo url('webmention') ?>">
```

Your site is now able to send and receive webmentions.

## More info

The webmentions are sent on the first visit of the page. Your server will check the presence of a `.webmentions/pings.json`-file in the folder for that page (e.g. `content/1-some-page/.webmentions/ping.json`). If it does not exist, the server will send webmentions and store the results in this file. If it contains `[]`, it means that no webmentions have been sent. Else, it will contain a JSON-object with URLs and statuses.

To re-sent webmentions, just delete or rename the file and visit the page. Please note that folders and files starting with a `.` will be hidden by macOS, feel free to Google.

This also means the first visit on any page will be slow. By default, the plugin only looks at the `text` field of your page, see below for more options.

## Added options

The plugin calls the 'webmentions.new' hook when a new webmention is received. I use it to log webmentions to a separate file, to keep track of them. You can even [mail](https://getkirby.com/docs/developer-guide/advanced/emails) yourself. Just be sure not to break the endpoint.

```php
kirby()->hook('webmentions.new', function($mention, $src, $target) {
  // do stuff!
  // $mention is an array
  // $src and $target are strings of urls
});
```

The content of `$mention` comes from [indieweb/php-comments](https://github.com/indieweb/php-comments).

```php
$mention = array(
  'type' => 'reply',
  'author' => array(
    'name' => 'Aaron Parecki',
    'photo' => 'http://aaronparecki.com/images/aaronpk.png',
    'url' => 'http://aaronparecki.com/'
  ),
  'published' => '2014-02-16T18:48:17-0800',
  'name' => 'Example Note',
  'text' => 'this text is displayed as the comment',
  'url' => 'http://aaronparecki.com/post/1'
);
```

The original plugin only looks at the 'text' field of a post. You can now add new fields to monitor for urls to mention. Note that the plugin only sends mentions on the first time the page is visited. (You can remove the `pings.json`-file from the `.webmentions`-folder in that page's folder and visit the page again to resend webmentions.)

```php
c::set('webmentions.fields', ['text', 'like_of', 'repost_of', 'in_reply_to']);
```

I didn't like the reverse display order, so now it's an option. `false` is oldest first, `true` is newest first.

```php
c::set('webmentions.reverse-order', false);
```

Other improvements:

- uses the router to search for pages
- passes 20 of 21 Endpoint Discovery Tests on [Webmention.rocks](https://webmention.rocks) (not test 13 unfortunately)
- when receiving webmentions, also checks the source page for (comma separated) urls in the 'syndication' field. So: if a webmention is received and a syndicated copy is mentioned, it still counts as a valid mention.
- bugfixes (and probably new bugs created! :D feel free to file issues)

## Purpose of files

- `lib/endpoint.php` contains the logic for *receiving* webmentions.
- `lib/mentions.php` contains logic for displaying a list of webmentions, but weirdly also for **sending** webmentions. Main function of interest here is `ping()`, which uses `trigger()`, which calls `discoverEndpoint()`. They use `remote::` from the Kirby Toolkit to make POST and GET calls.
- `lib/mention.php` contains logic for displaying one webmention.
- `lib/author.php` contains logic for displaying author information.
- `webmentions.php` just loads and registers things.
- see `snippets/*` for templates of the webmentions.

## Author

Based on [Bastian Allgeiers plugin](https://github.com/bastianallgeier/kirby-webmentions)

Sebastiaan Andeweg
