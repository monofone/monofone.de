<?php

include(__DIR__.'/vendor/Silex/autoload.php');

use Silex\Extension\TwigExtension;

$app = new Silex\Application();

$app['debug'] = true;
//$app->register(new MonologExtension(), array(
//    'monolog.class_path'    => __DIR__.'/vendor/monolog/src',
//    'monolog.logfile'       => __DIR__.'/development.log',    
////  'monolog.name'          => 'cars',
////  'monolog.level'         => Logger::INFO,
//));
 
$app->register(new TwigExtension(), array(
    'twig.path'       => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/Twig/lib',
));
 
$app->register(new Silex\Extension\UrlGeneratorExtension());
 
$app->before(function() use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});
 
 
$app->get('/', function() use ($app) {
  $url = $app['url_generator']->generate('page', array('name'=>'vita'));
  return $app->redirect($url);
});

$app->get('/page/{name}', function($name) use ($app) {
  return $app['twig']->render($name.'.twig');
})->bind('page');

$app->get('contact',function() use ($app){
  return $app['twig']->render('contact.twig');
})->bind('contact');

$app->post('contact/send',function() use ($app){
  return $app['twig']->render('contact.twig',array('send' => true));
})->bind('contact_send');

return $app;