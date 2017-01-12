<?php

namespace Kirby\Webmentions;

use A;
use Field;
use Obj;
use F;
use Media;
use Str;
use Url;
use C;
use Remote;
use Tpl;
use V;

class Author extends Obj {

  public $mention = null;
  public $data    = array();
  public $page    = null;
  public $name    = null;
  public $url     = null;
  public $photo   = null;

  public function __construct($mention) {

    $this->mention = $mention;
    $this->data    = $mention->data['author'];
    $this->page    = $mention->page;

    if(empty($this->data['url'])) {
      $this->data['url'] = $this->mention->data['url'];
    } elseif(empty($this->data['name']) or empty($this->data['photo'])) {
      $this->hcard();
    }

    if(empty($this->data['name'])) {
      $this->data['name'] = url::short(url::base($this->data['url']));
    }

    $this->field('url');
    $this->field('name');

  }

  public function field($key, $field = null) {
    if(is_null($field)) $field = $key;

    $value = a::get($this->data, $field);

    if($key == 'url' and !v::url($value)) {
      $value = null;
    }

    $this->$key = new Field($this->page, $key, esc($value));
  }

  public function hcard() {

    $filename  = f::safeName(url::host($this->data['url'])) . '-' . substr(sha1($this->data['url']),0,10) . '.json';
    $path      = c::get('webmentions.hcards', 'content/.hcards');
    $root      = kirby()->roots()->index() . DS . str_replace('/', DS, $path) . DS . $filename;

    if(f::exists($root)) {
      $this->data = json_decode(f::read($root), true);

    } else {
      require_once(dirname(__DIR__) . DS . 'vendor' . DS . 'mf2.php');

      $r = remote::get($this->data['url']);
      $mf2 = \Mf2\parse($r->content, $this->data['url']);

      $hcard = [
        'name' => false,
        'photo' => false,
        'url' => $this->data['url']
      ];

      if (array_key_exists('items', $mf2)
        and array_key_exists(0, $mf2['items'])
        and array_key_exists('type', $mf2['items'][0])
        and array_key_exists(0, $mf2['items'][0]['type'])
        and $mf2['items'][0]['type'][0] == 'h-card'
        and array_key_exists('properties', $mf2['items'][0])) {

        $mf2 = $mf2['items'][0]['properties'];

        if (array_key_exists('name', $mf2)) {
          if (is_string($mf2['name'])) {
            $hcard['name'] = $mf2['name'];
          } elseif (array_key_exists(0, $mf2['name']) and is_string($mf2['name'][0])) {
            $hcard['name'] = $mf2['name'][0];
          }
        }

        if (array_key_exists('photo', $mf2)) {
          if (is_string($mf2['photo']) and v::url($mf2['photo'])) {
            $hcard['photo'] = $mf2['photo'];
          } elseif (array_key_exists(0, $mf2['photo']) and v::url($mf2['photo'][0])) {
            $hcard['photo'] = $mf2['photo'][0];
          }
        }
      }

      f::write($root, json_encode($hcard));
      $this->data = $hcard;
    }
  }

  public function photo() {

    if(!is_null($this->photo)) return $this->photo;

    $extension = f::extension($this->data['photo']);
    $filename  = rtrim(sha1($this->url) . '.' . $extension, '.');
    $path      = c::get('webmentions.images', 'assets/images/mentions');
    $root      = kirby()->roots()->index() . DS . str_replace('/', DS, $path) . DS . $filename;
    $url       = kirby()->urls()->index() . '/' . $path . '/' . $filename;
    $photo     = new Media($root, $url);

    if(!$photo->exists()) {

      $image   = remote::get($this->data['photo']);
      $allowed = array('image/jpeg', 'image/png', 'image/gif');

      f::write($root, $image->content());

      $photo = new Media($root, $url);

      if(!in_array($photo->mime(), $allowed) or $photo->size() == 0) {
        $photo->delete();
      }

    }

    if(!$photo->exists() or !$photo->type() == 'image') {
      $photo = new Obj(array(
        'url'    => $this->data['photo'],
        'exists' => false
      ));
    }

    return $this->photo = $photo;

  }

  public function toHtml() {

    $snippet = kirby()->roots()->snippets() . DS . 'webmentions' . DS . 'author.php';

    if(!file_exists($snippet)) {
      $snippet = dirname(__DIR__) . DS . 'snippets' . DS . 'author.php';
    }

    return tpl::load($snippet, array(
      'author'  => $this,
      'mention' => $this->mention
    ));

  }

  public function __toString() {
    return (string)$this->toHtml();
  }

}