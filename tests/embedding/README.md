### Embedding Examples

- Using PHP: **php-page.php**
- Using JavaScript: **js-page.html**

#### Configuration for the Examples

NC User: `Admin22`  
Parent Domain: `localhost:8282` ( **php -S localhost:8282** in this directory )  
Parent Page URL (PHP): `http://localhost:8282/php-page.php`  
Parent Page URL (JS): `http://localhost:8282/js-page.html`  
Query Param Key: `my_param_key`  
Appointments Form URL: `http://nc22.localhost:9090/index.php/apps/appointments/embed/_o2wHj4yTTtQm9E%3D/form`

#### OCC Commands

Allowed Frame Ancestor Domain:  
`php occ config:app:set appointments "emb_afad_Admin22" --value "localhost:8282"`

Email Buttons:  
PHP Page: `php occ config:app:set appointments "emb_cncf_Admin22" --value "http://localhost:8282/php-page.php?my_param_key="`  
JS Page: `php occ config:app:set appointments "emb_cncf_Admin22" --value "http://localhost:8282/js-page.html?my_param_key="`

_* Make sure you use **your** username, domain and URLs in the OCC commands_