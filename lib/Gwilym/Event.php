<?php

/**
 * This class implements an Event bind and trigger system within PHP with support for persisting event bindings between page calls via the Gwilym_KeyStore class.
 *
 * Event triggers can either be global or isolated to a specific instance of any PHP object (requires PHP 5.2.0+ for spl_object_hash()).
 *
 * Event bindings can be any valid PHP callback (such as functions, static public methods, instance public methods or closures in PHP 5.3.0+). However, only functions and static public method bindings can be persisted, and bindings for instance-specific events will also not be persisted.
 *
 * @todo look at changing format of keys in the keystore and load all event bindings in one go, versus checking on each trigger
 */
class Gwilym_Event
{
	protected static $_defaultInstance = null;
	protected static $_instances = array();

	/** @var support named instances etc. */
	public static function factory (Gwilym_KeyStore_Interface $keystore = null, $name = null)
	{
		if ($keystore === null) {
			$keystore = Gwilym_KeyStore::factory();
		}

		if ($name === null) {
			if (self::$_defaultInstance === null) {
				self::$_defaultInstance = new self($keystore);
			}
			return self::$_defaultInstance;
		}

		if (!isset(self::$_instances[$name])) {
			self::$_instances[$name] = new self($keystore);
		}
		return self::$_instances[$name];
	}

	/**
	* storage for bindings for an event, in the format of event_id => callback
	*
	* @var array<callback>
	*/
	protected $_bindings = array();

	protected $_name;

	/**
	* storage for whether an event has had persistent bindings loaded yet, in the format of event_id => bool
	*
	* @var array<bool>
	*/
	protected $_loaded = array();

	public function __construct (Gwilym_KeyStore_Interface $keystore = null, $name = null)
	{
		if ($keystore === null) {
			$this->_keystore = Gwilym_KeyStore::factory();
		} else {
			$this->_keystore = $keystore;
		}

		$this->_name = $name;
	}

	/**
	* flushes all in-memory bindings, but does not affect persisted bindings - generally used for tests only to clear memory and force a load from persisted bindings
	*
	* @return void
	*/
	protected function _flushBindings ()
	{
		$this->_bindings = array();
		$this->_loaded = array();
	}

	/**
	* load any persisted bindings for a specific event, calling this on the same event name several times will only load once
	*
	* @param string $event
	* @return void
	*/
	protected function _load ($event)
	{
		if (isset($this->_loaded[$event]) && $this->_loaded[$event]) {
			return;
		}

		$bindings = $this->_keystore->multiGet('Gwilym_Event,bind,' . $event . ',*');

		foreach ($bindings as $binding)
		{
			if (strpos($binding, '::') === false)
			{
				// function callback
				$this->_bindings[$event][] = $binding;
			}
			else
			{
				// class::static callback
				$binding = explode('::', $binding);
				$this->_bindings[$event][] = array($binding[0], $binding[1]);
			}
		}

		$this->_loaded[$event] = true;
	}

	/**
	* bind a callback to an event with optional persistence between page loads
	*
	* @param object $object optional, the event can be object specific - binding to a specific object implies $persist = false as internal object ids are not unique between page loads
	* @param string $event event name
	* @param callback $callback callback, which can be a closure, an array(class, public static method), an array(object, public method), or a function name - binding a closure implies $persist = false as closures cannot be serialized
	* @param bool $persist optional, if true the binding will persist beyond this script execution (default false)
	* @throws Gwilym_Event_Exception_CannotPersistClosureBinding if an attempt is made to persist a closure as a callback
	* @throws Gwilym_Event_Exception_CannotPersistInstanceEvent if an attempt is made to persist a binding to an instance-specific event
	* @throws Gwilym_Event_Exception_CannotPersistInstanceBinding if an attempt is made to persist an instance-specific binding
	*/
	public function bind ($object, $event, $callback = null, $persist = null)
	{
		$args = func_get_args();

		if (!is_object($object)) {
			$object = null;
			$event = array_shift($args);
			$callback = array_shift($args);
			$persist = (bool)array_shift($args);
		}

		if ($object) {
			$event = spl_object_hash($object) . '#' . $event;
		}

		$this->_bindings[$event][] = $callback;

		if (!$persist) {
			return true;
		}

		if (Gwilym_Reflection::isClosure($callback)) {
			throw new Gwilym_Event_Exception_CannotPersistClosureBinding;
		}

		if ($object) {
			throw new Gwilym_Event_Exception_CannotPersistInstanceEvent;
		}

		if (is_array($callback)) {
			if (is_object($callback[0])) {
				throw new Gwilym_Event_Exception_CannotPersistInstanceBinding;
			}

			// store array callbacks as strings to undo later when loading bindings
			$callback = $callback[0] . '::' . $callback[1];
		}

		$this->_keystore->set('Gwilym_Event,bind,' . $event . ',' . md5($callback), $callback);
	}

	/**
	* unbinds a callback from an event, including any persisted bindings
	*
	* @param object $object optional, the event can be object specific - binding to a specific object implies $persist = false as internal object ids are not unique between page loads
	* @param string $event event name
	* @param callback $callback callback, which can be a closure, an array(class, static method) or a function name - binding a closure implies $persist = false as closures cannot be serialized
	* @return void
	*/
	public function unbind ($object, $event, $callback = null)
	{
		$args = func_get_args();
		$persist = true;

		if (!is_object($object)) {
			$object = null;
			$event = array_shift($args);
			$callback = array_shift($args);
		}

		if ($object) {
			$event = spl_object_hash($object) . '#' . $event;
			$persist = false; // cannot persist instance events so don't bother trying to delete them
		}

		if (Gwilym_Reflection::isClosure($callback)) {
			$persist = false; // cannot persist closure bindings so don't bother trying to delete them
		}

		if (is_array($callback) && is_object($callback[0])) {
			$persist = false; // cannot persist instance bindings so don't bother trying to delete them
		}

		if (isset($this->_bindings[$event])) {
			foreach ($this->_bindings[$event] as $binding) {
				if ($binding === $callback) {
					unset($this->_bindings[$event]);
				}
			}
		}

		if (!$persist) {
			// stop here if the type of event binding we're trying to unbind cannot be persisted
			return;
		}

		// delete persisted bindings
		if (is_array($callback)) {
			$callback = $callback[0] . '::' . $callback[1];
		}

		$this->_keystore->delete('Gwilym_Event,bind,' . $event . ',' . md5($callback));
	}

	/**
	* trigger an event
	*
	* @param object $object optional
	* @param string $event
	* @param mixed $data optional
	* @return Gwilym_Event
	*/
	public function trigger ($object, $event = null, $data = null)
	{
		$args = func_get_args();

		if (is_object($object))
		{
			// triggering instance-specific event
			$key = spl_object_hash($object) . '#' . $event;
		}
		else
		{
			// triggering global event
			$data = $event;
			$event = $object;
			$key = $event;
			$object = null;
			$this->_load($event);
		}

		$instance = new self($this->_keystore, $this->_name);
		$instance->type($event);
		$instance->data = $data;

		if (!isset($this->_bindings[$key]))
		{
			return $instance;
		}

		foreach ($this->_bindings[$key] as $binding)
		{
			$result = call_user_func($binding, $instance);

			if ($result === false)
			{
				$instance->stopPropagation();
				$instance->preventDefault();
				break;
			}

			if ($instance->isPropagationStopped())
			{
				break;
			}
		}

		return $instance;
	}

	// ====================

	protected $_defaultPrevented = false;

	public function isDefaultPrevented ()
	{
		return $this->_defaultPrevented;
	}

	public function preventDefault ()
	{
		$this->_defaultPrevented = true;
	}

	protected $_propagationStopped = false;

	public function isPropagationStopped ()
	{
		return $this->_propagationStopped;
	}

	public function stopPropagation ()
	{
		$this->_propagationStopped = true;
	}

	protected $_type;

	public function type ($type = null)
	{
		if (func_num_args())
		{
			$this->_type = $type;
			return $this;
		}
		return $this->_type;
	}

	/** @var mixed data payload provided by trigger() */
	public $data = null;
}
