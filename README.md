ZF2-w2ui-CRUD-generator
=======================
It saves me a lot of time because it does the routine job when starting new table CRUD. Script creates and/or updates Zend Framework 2 modules, controllers, models, views, config files and folders. Generated tree uses w2ui (http://w2ui.com) Javascript library instead of ZF2 grids and forms.

To start using
==============
   - install ZF2 skeleton-application as described in http://framework.zend.com/manual/2.0/en/user-guide/skeleton-application.html
   - include *w2ui-1.4.js* and *w2ui-1.4.css* in your application *layout.phtml* file 
 
Usage
======
To generate CRUD for table 'apples':
   - create MySQL table 'apples' with at least 3 columns
   - place file *createcrud_w2ui.php* in your ZF2 applications root folder 
   - in console type 

        php createcrud_w2ui.php food apples


Now you can view and edit table 'apples' in your site at */food/apples* path.
