sentient
========

Small and lightweight MVC framework for PHP web apps. It provides a very flexible structure for your apps, without forcing you to abide to huge documentation, a specific file and naming structure, etc.


Some features
-------------

###Object

. Obj-c style automagic getters named after the properties, e.g., if there is a
property named 'bar' on the object 'foo', you can access it with $foo->bar()
NOTE: currently private and protected properties can be called from outside!

. Obj-C style automagic setter, e.g., $foo->setBar($bar). This automagic setter
notifies all registered observers that this property has changed value (and only
if it actually changed!), using the Obj-C style willChangeValueForKey and
didChangeValueForKey

. 'isBar' and 'hasBar' style automagic getters for each property, that always return
a boolean.

. 'emptyBar' style automagic getter, that checks if 'bar' is empty.

. Support for observers, for watching a specific property. These registered
observers get notified whenever their watched property changes. Observer objects
(which are also derived from the Object class) must implement observeValueForKeyPath,
which is what will be called by the observed object.

. Obj-C style generic setValueForKey and valueForKey as setter and getter, besides
the automagic ones.

. Support for delegates. Delegates are an easy way to provide hooks on a class'
behaviour. Object A executes action 'a' and, by design, fires a delegate method.
If Object B registers itself as delegate of Object A and implements delegate
method 'a', it will be called by A in runtime.



REQUIREMENTS:
array_replace_recursive() >= 5.3.0
