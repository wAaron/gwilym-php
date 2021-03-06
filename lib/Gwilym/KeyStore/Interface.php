<?php

interface Gwilym_KeyStore_Interface
{
	/**
	* Set key $key to value $value
	*
	* @param string $key
	* @param string $value
	* @return bool true
	* @throws Gwilym_KeyStore_Exception
	*/
	public function set ($key, $value);

	/**
	* Get value of key $key
	*
	* @param string $key
	* @return mixed string value of key $key
	* @throws Gwilym_KeyStore_Exception
	*/
	public function get ($key);

	/**
	* Checks if key $key exists
	*
	* @param string $key
	* @return bool true if key $key exists otherwise false
	* @throws Gwilym_KeyStore_Exception
	*/
	public function exists ($key);

	/**
	* Deletes key $key
	*
	* @param string $key
	* @return bool true
	* @throws Gwilym_KeyStore_Exception
	*/
	public function delete ($key);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function multiSet ($keyValues);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function multiGet ($pattern);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function multiDelete ($pattern);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function increment ($key, $value = 1);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function decrement ($key, $value = 1);

	/**
	* @throws Gwilym_KeyStore_Exception
	*/
	public function append ($key, $value);

	/**
	* Define (or get) the prefix to use for all key operations on this keystore instance.
	*
	* @param string $prefix
	* @return mixed if $prefix is null, return the current prefix, otherwise return true if the prefix was changed or false if the prefix was not changed due to it being locked
	*/
	public function prefix ($prefix = null);

	/**
	* Call this to lock the prefix to it's current value to prevent accidental altering of the prefix value.
	*
	* @return void
	*/
	public function lockPrefix ();
}
