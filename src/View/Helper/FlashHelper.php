<?php

declare(strict_types=1);

namespace XFlash\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;

/**
 * Flash helper
 */
class FlashHelper extends Helper
{
	/**
	 * @var array
	 */
	public array $helpers = [];
	/**
	 * Default configuration.
	 *
	 * @var array
	 */
	protected array $_defaultConfig = [];

	/**
	 * @return string
	 */
	public function render(): string
	{
		$html     = '';
		$messages = Configure::consume('XFlash') ?: $this->_View->getRequest()->getSession()->consume('X-Flash') ?: [];
		foreach ($messages as $message) {
			$element = $this->_View->element('flash/' . $message['type'], $message, ['plugin' => false]);
			$html .= $element;
		}
		return $html;
	}
}
