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
		$tag = $this->getTagRegex();

		$this->Lexer->addSpecialPattern(
			'\{\{' . $tag . '>[^\|\}]+(?:\|[^\}]+)?\}\}',
			$mode,
			'plugin_localopen'
		);
	}

	private function getTagRegex()
	{
		$tag = trim($this->getConf('tag') ?: 'lopen');
		return preg_quote($tag, '/');
	}



	public function handle($match, $state, $pos, Doku_Handler $handler)
	{

		$tag = $this->getTagRegex();
		if (!preg_match('/\{\{' . $tag . '>([^\|\}]+)(?:\|([^\}]+))?\}\}/i', $match, $matches)) {
			return false;
		}

		$path = trim($matches[1], "\"'");
		$title = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : $path;

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
		
		if (!$token || !$port) {
			return false;
		}

		$url = 'http://127.0.0.1:' . $port .
			   '/open?path=' . rawurlencode($path) .
			   '&token=' . rawurlencode($token);

		$renderer->doc .=
			'<a class="localopen-link" title="' . hsc($path) . '" href="' . hsc($url) . '">' .
			'<img src="' . hsc(DOKU_BASE . 'lib/plugins/localopen/images/lopen.svg') . '" alt="" class="localopen-icon" /> ' .
			$title .
			'</a>';

		return true;
	}
}
