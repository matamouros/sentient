Sentient
========

Small and lightweight MVC framework for PHP web apps. It provides a very
flexible structure for your apps, giving you some useful tools such as Obj-C
style delegates, observers and automagic getters and setters, just to name a
few. Unlike bigger and more complex frameworks, such as Zend's, Sentient goes
out of its way to not bind you to pre-determined schemes of file and class
naming and application structure.


Some features
-------------

### Object

 * Obj-C style automagic getters named after the properties, e.g., if there is a
   property named `bar` on the object `foo`, you can access it with `$foo->bar()`.
   
   __NOTE:__ currently private and protected properties can be called from outside!

 * Obj-C style automagic setter, e.g., `$foo->setBar($bar)`. This automagic setter
   notifies all registered observers that this property has changed value (only
   if it actually changed!), using the Obj-C style `willChangeValueForKey` method and
   `didChangeValueForKey`.

 * `isBar` and `hasBar` style automagic getters for each property, that always return
   a boolean.

 * `emptyBar` style automagic getter, that checks if the `bar` attribute is empty.

 * Support for observers, for watching a specific property. These registered
   observers get notified whenever the property they are watching changes. Observer
   objects (which are also derived from the Object class) must implement
   `observeValueForKeyPath`, which is what will be called by the observed object.

 * Obj-C style generic `setValueForKey` and `valueForKey` as setter and getter,
   besides the automagic ones.

 * Support for delegates. Delegates are an easy way to provide hooks on a class'
   behaviour. Object A executes action 'a' and, by design, fires a delegate method.
   If Object B is registered as delegate of Object A and implements the delegate
   method 'a', it will be called by A in runtime.


### Config

 * JSON configuration files for your application.

 * Possibility of using `${VAR}` variables in values, that will be expanded to
   the corresponding configuration key's value.
   
   __NOTE__: There is currently only one pass for expanding these placeholders,
   meaning that the complete JSON structure is first loaded as an array and then
   the variables will try to be resolved on one pass. Second and deeper levels of
   variable referencing might never be expanded.
   
 * Runtime loading of configuration, no configuration "compiling" time is necessary.


### Routing

 * Simplified routing for simple applications, you just need to provide your own
   controller, which acts as a delegate. The only code you need is:

   ```
   $router = new Sentient\SimpleHttpRouter();
   $router->setDelegate(new HttpController());
   $router->init();
   $router->run();
   ```
   
   This simple routing will map the following URLs to methods on your controller:

   ```
   /foo         => foo()
   /foo-bar     => fooBar()
   /foo/bar     => foo_bar()
   /foo/foo-bar => foo_fooBar()
   ```
   
 * If you require more customisation for your routes and controllers, you have the
   option of using the advanced routing, which allows you to finely specify different
   combinations of controllers, methods and parameters for every route you register.


Requirements
------------

 . `array_replace_recursive()` >= 5.3.0
