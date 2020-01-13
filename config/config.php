<?php return array (
  'logs' => 
  array (
    'path' => 'backup/logs',
    'type' => 'file',
  ),
  'DB' => 
  array (
    'type' => 'mysqli',
    'tablePre' => 'lb_',
    'read' => 
    array (
      0 => 
      array (
        'host' => '106.14.62.211:3306',
        'user' => 'root',
        'passwd' => 'root',
        'name' => 'lb',
      ),
    ),
    'write' => 
    array (
      'host' => '106.14.62.211:3306',
      'user' => 'root',
      'passwd' => 'root',
      'name' => 'lb',
    ),
  ),
  'interceptor' => 
  array (
    0 => 'themeroute@onCreateController',
    1 => 'layoutroute@onCreateView',
    2 => 'plugin',
  ),
  'langPath' => 'language',
  'viewPath' => 'views',
  'skinPath' => 'skin',
  'classes' => 'classes.*',
  'rewriteRule' => 'url',
  'theme' => 
  array (
    'pc' => 
    array (
      'mobile' => 'default',
      'sysdefault' => 'default',
      'sysseller' => 'default',
    ),
    'mobile' => 
    array (
      'mobile' => 'default',
      'sysdefault' => 'default',
      'sysseller' => 'default',
    ),
  ),
  'timezone' => 'Etc/GMT-8',
  'upload' => 'upload',
  'dbbackup' => 'backup/database',
  'safe' => 'cookie',
  'lang' => 'zh_sc',
  'debug' => '0',
  'configExt' => 
  array (
    'site_config' => 'config/site_config.php',
    'name_config' => 'config/name_config.php',
  ),
  'encryptKey' => '0e1910a3b4d7f374c40c3e50a06cf6b2',
  'authorizeCode' => '2017062899877464832',
)?>