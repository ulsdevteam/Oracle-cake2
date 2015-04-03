# Oracle Datasource for CakePHP 2.x

This datasource provides Oracle database connectivity via the oci_* functions for [CakePHP](http://cakephp.org) 2.x.  It extends DboSource, but overrides any PDO functionality.

## Source

Original source presumed to be a CakePHP 1.x datasource.  This was modified by odin88 in 2012 as described in [Using Oracle in CakePHP 2.0](http://www.hassanbakar.com/2012/01/09/using-oracle-in-cakephp-2-0/).  It was formerly available at the now-defunct snipt.org.  Clinton Graham picked this up in 2015 for the [University of Pittsburgh](http://www.pitt.edu).

## License
[MIT License](http://www.opensource.org/licenses/mit-license.php)

## Using
To install, clone or place a copy of this repository in your app/Plugins directory, e.g. app/Plugins/OracleDS.  Load the plugin in your app/Config/bootstrap.php, e.g. `CakePlugin::load('OracleDS');`.  Reference the datasource from your app/Config/database.php, e.g. `'datasource' => 'OracleDS.Oracle',`.
