<?php

/**
 * This interface is intentionally empty. Controllers can implement this interface which will then be checked by the
 * Route classes to dermine if this controller can be accessed by the HTTP method specified by the user agent.
 *
 * @todo consider changing this to Allow* rather than *Only
 *
 * e.g. This controller will only be accessible via a GET request:
 * class Controller_Foo implements Gwilym_Controller_GetOnly { ... }
 */
interface Gwilym_Controller_GetOnly extends Gwilym_Controller_MethodSpecific { }
