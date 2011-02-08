Framework/1 PHP Port

Original port by Rick Osborne <http://rickosborne.org/>
from the original Framework/1 source by Sean Corfield and Ryan Cogswell.

Framework/1: https://github.com/seancorfield/fw1/

ABOUT

This is an extremely hacked-together port.  Like, whoa.  Use it at your own risk.

NOTES

There are, of course a few differences.

 * Subsystem and bean factory code have been removed.  (Not because
   they wouldn't work, but because I didn't need them.)
   
 * Views don't have magically-local scope access to functions like
   buildUrl().  You'll need to use $fw->buildUrl(), etc, instead.

 * There's a helper function dump() to make you feel right at home.
   
 * Since there's no Application scope, there's no real caching.
 
 * As previous, framework setting overrides are passed in via constructor.
 
 * The whole FW1Obj thing is a total hack to work around a PHP 5.x
   bug that passed arguments to __call methods by value instead of
   by reference.  Yes, it sucks.  I know.  I don't like it, and it
   will hopefully get refactored later.

FIXES

 * As of b17f9b497a971ff3c60f, service calls are magical, just like CF.
 
LICENSE

Copyright 2011, Rick Osborne

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
