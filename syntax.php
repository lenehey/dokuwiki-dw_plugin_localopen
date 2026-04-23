<?php
/**
 * DokuWiki Plugin localopen (Syntax Component)
 *
 * @license MIT
 * @author  Leonard Heyman
 */

class syntax_plugin_localopen extends \dokuwiki\Extension\SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'normal';
    }

    public function getSort()
    {
        return 299;
    }

    public function connectTo($mode)
    {
        $tag = preg_quote($this->getConf('tag'), '/');

        $this->Lexer->addSpecialPattern(
            '\[\[' . $tag . '>[^|\]]+\|[^]]+\]\]',
            $mode,
            'plugin_localopen'
        );
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $tag = preg_quote($this->getConf('tag'), '/');

        preg_match('/\[\[' . $tag . '>([^|]+)\|([^]]+)\]\]/i', $match, $matches);

        $path = str_replace('"', '', $matches[1]);
        $title = $matches[2];

        return [
            'path'  => $path,
            'title' => $title,
        ];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') return false;

        $path  = $data['path'];
        $title = hsc($data['title']);

        $token = $this->getConf('token');
        $port  = $this->getConf('port');

        $url = 'http://127.0.0.1:' . $port . '/open?path=' . rawurlencode($path) . '&token=' . rawurlencode($token);

        $href = hsc($url);
        $title_attr = hsc($path);

        $icon = DOKU_BASE . 'lib/plugins/localopen/images/lopen.svg';

        $renderer->doc .=
            '<a class="localopen-link" title="' . $title_attr . '" href="' . $href . '" onclick="fetch(this.href,{mode:\'no-cors\'}); return false;">' .
            '<img src="' . hsc($icon) . '" alt="" class="localopen-icon" /> ' .
            $title .
            '</a>';

        return true;
    }
}
