<?php

declare(strict_types=1);

namespace XFlash\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Form\Form;
use Cake\Http\Session;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\Utility\Inflector;
use Cake\Utility\Text;

/**
 * Flash component
 * @note If you want to use this FlashCompoment with JS-Scripts to show the Messages and use callbacks
 * you might want to you use something like https://docs.laminas.dev/laminas-json/advanced/
 * since json_encode wraps callbacks in quotes
 * 
 * @method mixed success(string $text, array $options=[])
 * @method mixed error(string $text, array $options=[])
 * @method mixed warning(string $text, array $options=[])
 * @method mixed info(string $text, array $options=[])
 * @method mixed errors(\Cake\ORM\Entity $entity, array $options=[])
 */
class FlashComponent extends Component
{
	/**
	 * Default configuration.
	 *
	 * @var array
	 */
	protected array $_defaultConfig = [
		'header'       => 'X-Flash',
		'renderHeader' => [
			'ajax' => true,
		],
	];

	/**
	 * @param \Cake\Controller\ComponentRegistry $registry A ComponentRegistry for this component
	 * @param array $config Array of config.
	 */
	public function __construct(ComponentRegistry $registry, array $config = [])
	{
		$this->_defaultConfig = array_merge($this->_defaultConfig, $config);
		parent::__construct($registry, $config);
	}

	/**
	 * @param $func
	 * @param $args
	 * @return mixed
	 */
	public function __call($func, $args)
	{
		$message = $this->_addMessage([
			'type'    => $func,
			'message' => $args[0],
			'params'  => $args[1] ?? [],
		]);

		return $message;
	}

	public function __toString()
	{
		return json_encode($this->_getMessages());
	}

	public function beforeRender()
	{
		$requestTypes = array_keys(array_filter($this->_defaultConfig['renderHeader']));
		if (!$this->getController()->getRequest()->is(array_merge(['json'], $requestTypes))) {
			Configure::write('XFlash', $this->_getMessages());
		}
	}

	public function afterFilter()
	{
		$requestTypes = array_keys(array_filter($this->_defaultConfig['renderHeader']));
		if ($this->getController()->getRequest()->is(array_merge(['json'], $requestTypes))) {
			if (!empty($this->getController()->getResponse()->getHeader('Location'))) {
				//dont consume flash-messages on redirect. jQuery doesn't have access on headers of 302-responses
				return;
			}
			$this->getController()->setResponse(
				$this->getController()->getResponse()->withHeader($this->_defaultConfig['header'], json_encode(array_values($this->_getMessages())))
			);
		}
	}

	/**
	 * @return Session
	 */
	protected function _getSession(): Session
	{
		return $this->getController()->getRequest()->getSession();
	}

	protected function _extractEntityErrors(Entity|Form $entity, string $type, array $params)
	{
		$fields   = $entity->getErrors() ?: [];
		$messages = [];
		foreach ($fields as $field => $errors) {
			if (is_string($errors)) {
				$text       = '[' . ucfirst($field) . '] ' . $errors;
				$messages[] = [
					'type'    => 'error',
					'message' => $text,
					'params'  => $params,
				];
				continue;
			}
			foreach ($errors as $key => $text) {
				if (is_string($text)) {
					$text       = '[' . ucfirst($field) . '] ' . $text;
					$messages[] = [
						'type'    => Inflector::singularize($type),
						'message' => $text,
						'params'  => $params,
					];
				} else {
					array_walk_recursive($errors, function ($text) use (&$messages, $field, $params, $type) {
						if (is_string($text)) {
							$text       = '[' . Inflector::humanize($field) . '] ' . $text;
							$messages[$text] = [
								'type'    => Inflector::singularize($type),
								'message' => $text,
								'params'  => $params,
							];
						}
					});
				}
			}
		}
		// $messages = $this->_removeDuplicates($messages); //prevents same-message-multiplication
		return $messages;
	}

	/**
	 * @param array $message
	 * @return mixed
	 */
	protected function _addMessage(array $message): static
	{
		if (empty($message['message'])) {
			return $this;
		}
		$messages = $this->_getMessages();
		if (is_a($message['message'], Entity::class) || is_a($message['message'], Form::class)) {
			$message['params'] = ['escape' => false] + ($message['params'] ?? []);
			$messages          = array_merge($messages, $this->_extractEntityErrors($message['message'], $message['type'], $message['params']));
		} else {
			if ($message['params']['escape'] ?? true) {
				$message['message'] = h($message['message']);
			}
			$messages[] = $message;
		}
		$messages = $this->_removeDuplicates($messages);
		$this->_getSession()->write('XFlash', $messages);
		return $this;
	}

	protected function _removeDuplicates(array $messages): array
	{
		$messages = array_map('json_encode', $messages);
		$messages = array_unique($messages);
		$messages = array_map(fn ($msg) => json_decode($msg, true), $messages);
		return $messages;
	}

	/**
	 * @return mixed
	 */
	protected function _getMessages(bool $consume = true): array
	{
		if ($consume) {
			return $this->_getSession()->consume('XFlash') ?: [];
		}
		return $this->_getSession()->read('XFlash') ?: [];
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	protected function _getConfig($key)
	{
		return $this->_defaultConfig[$key] ?? null;
	}

	public function toHeader()
	{
		return [$this->_defaultConfig['header'], (string)$this];
	}
}
