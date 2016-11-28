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

          try {
            $endpoint->start();
            if (page('webmention')) {
              header::status(202);
              tpl::set('status', 'success');
              tpl::set('alert', null);
              return site()->visit('webmention');
            } else {
              echo response::success('Yay', 202);
            }
          } catch(Exception $e) {
            if (page('webmention')) {
              echo response::error($e->getMessage());
            } else {
              header::status(400);
              tpl::set('status', 'error');
              tpl::set('alert', $e->getMessage());
              return site()->visit('webmention');
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

    $source = remote::get($src);
    $data   = \Mf2\parse($source->content);
    $result = \IndieWeb\comments\parse($data['items'][0], $target, 1000);

    // I need the router to think we're on GET.
    $HACK = $_SERVER['REQUEST_METHOD'];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    // Find the target page
    $route = kirby()->router->run(url::path($target));
    $page = call($route->action(), $route->arguments());

    // Restore the original value.
    $_SERVER['REQUEST_METHOD'] = $HACK;

    if(!$page->isErrorPage()) {

      // Do they mention us?
      if(!str::contains($source->content, $target)) {
        $found = false;

        // Maybe they did mention a syndicated link?
        if ($page->syndication()->isNotEmpty()) {
          foreach ($page->syndication()->split() as $syndication) {
            if (str::contains($source->content, $syndication)) {
              $result = \IndieWeb\comments\parse($data['items'][0], $syndication);
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

      $json = json_encode($result);
      $hash = sha1($json);
      $file = $page->root() . DS . '.webmentions' . DS . $time . '-' . $hash . '.json';

      f::write($file, $json);

      kirby()->trigger('webmentions.new', [$result, $src, $target]);

      return true;

    } else {
      throw new Exception('Invalid page');
    }

  }

}