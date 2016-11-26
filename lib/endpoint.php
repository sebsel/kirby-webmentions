<?php

namespace Kirby\Webmentions;

use Header;
use V;
use Str;
use Url;
use Response;
use F;
use R;
use Exception;
use Tpl;

class Endpoint {

  public function __construct() {

    $endpoint = $this;

    if($page = page('webmention') and kirby()->path() == $page->uri()) {

      if(r::is('post')) {

        try {
          $endpoint->start();
          header::status(202);
          tpl::set('status', 'success');
          tpl::set('alert', null);
        } catch(Exception $e) {
          header::status(400);
          tpl::set('status', 'error');
          tpl::set('alert', $e->getMessage());
        }

      } else {
        tpl::set('status', 'idle');
      }

    } else {

      kirby()->routes(array(
        array(
          'pattern' => 'webmention',
          'method'  => 'GET|POST',
          'action'  => function() use($endpoint) {

            try {
              $endpoint->start();
              echo response::success('Yay', 202);
            } catch(Exception $e) {
              echo response::error($e->getMessage());
            }
          }
        )
      ));

    }

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

    $data   = \Mf2\fetch($src);
    $result = \IndieWeb\comments\parse($data['items'][0], $target);

    if(empty($result)) {
      throw new Exception('Probably spam');
    }

    // I need the router to think we're on GET.
    $HACK = $_SERVER['REQUEST_METHOD'];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    // Find the target page
    $route = kirby()->router->run(url::path($target));
    $page = call($route->action(), $route->arguments());

    // Restore the original value.
    $_SERVER['REQUEST_METHOD'] = $HACK;

    if(!$page->isErrorPage()) {

      if(!empty($result['published'])) {
        $time = strtotime($result['published']);
      } else {
        $time = time();
        $result['published'] = date('c');
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