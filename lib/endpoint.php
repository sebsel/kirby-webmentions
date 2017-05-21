<?php

namespace Kirby\Webmentions;

use Header;
use V;
use Str;
use Url;
use Remote;
use Response;
use F;
use R;
use Exception;
use Tpl;

class Endpoint {

  public function __construct() {

    $endpoint = $this;

    kirby()->routes([
      [
        'pattern' => 'webmention',
        'method'  => 'GET|POST',
        'action'  => function() use($endpoint) {

          if (!get('source') or !get('target')) {
            return site()->visit('webmention');
          }

          // If a page 'webmention' exists, give info to the template
          if (page('webmention')) {
            try {
              $endpoint->start();
              header::status(202);
              tpl::set('status', 'success');
              tpl::set('alert', null);
              return site()->visit('webmention');
            } catch(Exception $e) {
              header::status(400);
              tpl::set('status', 'error');
              tpl::set('alert', $e->getMessage());
              return site()->visit('webmention');
            }

          // If there is no such page, just respond
          } else {
            try {
              $endpoint->start();
              echo response::success('Yay', 202);
            } catch(Exception $e) {
              echo response::error($e->getMessage());
            }
          }
        }
      ]
    ]);
  }

  public function start() {

    $src    = get('source');
    $target = get('target');

    if(!v::url($src)) {
      throw new Exception('Invalid source');
    }

    if(!v::url($target)) {
      throw new Exception('Invalid target');
    }

    if(!str::contains($target, site()->url())) {
      throw new Exception('Invalid target');
    }

    require_once(dirname(__DIR__) . DS . 'vendor' . DS . 'mf2.php');
    require_once(dirname(__DIR__) . DS . 'vendor' . DS . 'comments.php');

    // Private mentions
    $data = [];
    if(get('code')) {
      $r = remote::head($src);

      if(!isset($r->headers['WWW-Authenticate'])
      or !$r->headers['WWW-Authenticate'] !== 'Bearer') throw new Exception('Only support for WWW-Authenticate: Bearer');

      if(!isset($r->headers['Link'])
      or !preg_match('!\<(.*?)\>;\s*rel="?(.*?\s?)token_endpoint(\s?.*?)"?!', $r->headers['Link'], $match)) throw new Exception('No token endpoint');

      $r = remote::post($match[1], ['data' => [
        'grant_type' => 'authorization_code',
        'code' => get('code')
      ]]);

      $r = json_decode($r->content);

      $data = ['headers' => ['Authorization: Bearer '.$r->access_token]];
    }

    // Get source (with or without token)
    $source = remote::get($src, $data);
    $mf2   = \Mf2\parse($source->content, $src);
    if(!isset($mf2['items'][0])) throw new Exception('No Microformats h-* found');
    $result = \IndieWeb\comments\parse($mf2['items'][0], $target, 1000, 20);

    // php-comments does not do rel=author
    if ($result['author']['url'] === false
      and array_key_exists('rels', $mf2)
      and array_key_exists('author', $mf2['rels'])
      and array_key_exists(0, $mf2['rels']['author'])
      and is_string($mf2['rels']['author'][0])) {
        $result['author']['url'] = $mf2['rels']['author'][0];
    }

    $path = url::path($target);
    if($path == '') $page = page('home');
    else {
      // I need the router to think we're on GET.
      $HACK = $_SERVER['REQUEST_METHOD'];
      $_SERVER['REQUEST_METHOD'] = 'GET';

      // Find the target page
      $route = kirby()->router->run($path);
      $page = call($route->action(), $route->arguments());

      // Restore the original value.
      $_SERVER['REQUEST_METHOD'] = $HACK;
    }

    if(!$page->isErrorPage()) {

      // Do they mention us?
      if(!str::contains($source->content, $target)) {
        $found = false;

        // Maybe they did mention a syndicated link?
        if ($page->syndication()->isNotEmpty()) {
          foreach ($page->syndication()->split() as $syndication) {
            if (str::contains($source->content, $syndication)) {
              $result = \IndieWeb\comments\parse($mf2['items'][0], $syndication);
              $found = true;
              break;
            }
          }
        }

        if (!$found){
          throw new Exception('Probably spam');
        }
      }

      if(!empty($result['published'])) {
        $time = strtotime($result['published']);
      } else {
        $time = 0;
      }

      if(isset($result['text'])
      and strlen($result['text']) < 10
      and \Emoji\is_single_emoji($result['text']))
        $result['type'] = 'reacji';

      $result['source'] = $src;
      if(get('code')) $result['private'] = true;

      $json = json_encode($result);
      $hash = sha1($src);
      $file = $page->root() . DS . '.webmentions' . DS . $time . '-' . $hash . '.json';

      f::write($file, $json);

      kirby()->trigger('webmentions.new', [$result, $src, $target]);

      return true;

    } else {
      throw new Exception('Invalid page');
    }

  }

}