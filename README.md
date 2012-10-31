#phpEasyMin
phpEasyMin is a simple JavaScript and CSS "build" tool for creating user distribution packages from unminimized source code. phpEasyMin uses Google's Closure Compile Service and CSSMin for minimizin.

##Installation
1. Unzip the [phpEasyMin zip](https://github.com/oyejorge/phpEasyMin/downloads) package into a folder on your webserver
2. Make sure the _data_ directory is writable
3. Start minimizing!

Note: phpEasyMin was not designed for use on publicly accessible servers!

##Setting Up a Project
1. Click "Add New Project" in the phpEasyMin toolbar
2. Enter a _name_, _source path_ and _destination path_
   * The _name_ can be anything you like
   * The _source path_ should be the folder location of code you want to minimize
   * The _destination path_ should be a writable folder phpEasyMin can send the minimized contents to
3. Click Save

##Project Configuration
The following files can be added to your projects to control how phpEasyMin performs 
* _[source path]/.easymin/ignore_types_
  * A space separated list of file types that phpEasyMin should not copy. For example, ".psd" would prevent phpEasyMin from copying any photoshop files.
* _[source path]/.easymin/ignore_prefixes_
  * A space separated list of filename prefixes that phpEasyMin should not copy. For example, a value of "skip_" would prevent phpEasyMin from copying any file that starts with "skip_".
* _[source path]/.easymin/noshrink_paths_
  * A newline separated list of paths (relative to the _source path_) that phpEasyMin should not minimize.

##Combining Files
You can alos instruct phpEasyMin to combine multiple source code files into a single file with the use of _.combine_ files. Each line of a .combine file will be treated as either a file path (relative to the .combine file location) or content. For a line to be handled as a file path, it should start with either a '.' or '/' character.

An example of a _.combine_ file can be seen in the [gpFinder repository](https://github.com/oyejorge/gpFinder/tree/master/js). When phpEasyMin minimizes the gpFinder project, it will use the instructions in _elfinder.js.combine_ to create a _elfinder.js_.
