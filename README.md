# Flash plugin for CakePHP

## Add this to your AppController::initialize()

<pre>
$this->loadComponent('XFlash.Flash', [
	'renderHeader' => [
		'ajax' => false,
	],
]);
</pre>

## Add this to your AppView::initialize()

<code>
$this->loadHelper('XFlash.Flash', []);
</code>

## Use it like the default CakePHP Flash-Plugin

<code>
$this->Flash->success(__('Big success'), $options);
</code>