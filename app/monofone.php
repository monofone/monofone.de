<?php

include(__DIR__.'/vendor/Silex/autoload.php');

use Silex\Extension\TwigExtension;

$app = new Silex\Application();

$app['debug'] = false;

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

 $app->register(new Silex\Extension\FormExtension(), array(
    'form.class_path' => __DIR__ . '/vendor/symfony/src'
));

$app->register(new Silex\Extension\TranslationExtension(), array(
  'locale_fallback' => 'en',
  'translation.class_path' => __DIR__ . '/vendor/symfony/src',
  'translator.messages' => array()
));
 
$app->register(new Silex\Extension\SymfonyBridgesExtension(), array(
    'symfony_bridges.class_path' => __DIR__ . '/vendor/symfony/src'
));


$app->register(new Silex\Extension\UrlGeneratorExtension());
 
$app->before(function() use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});
 
$app->error(function() use($app) {
  return $app['twig']->render('error.twig');
});
 
$app->get('/', function() use ($app) {
  $url = $app['url_generator']->generate('page', array('name'=>'vita'));
  return $app->redirect($url);
});

$app->get('/page/{name}', function($name) use ($app) {
  return $app['twig']->render($name.'.twig');
})->bind('page');

$app->match('contact',function() use ($app){
    $form = $app['form.factory']
	    ->createBuilder('form')
	    ->add('email', 'email', array('label' => 'E-Mail:'))
	    ->add('message', 'textarea', array('label' => 'Message:'))
	    ->getForm();

    if ('POST' == $app['request']->getMethod()) {
        $form->bindRequest($app['request']);
        if ($form->isValid()) {
            $data = $form->getData();

            require_once __DIR__ . '/vendor/swiftmailer/lib/swift_required.php';
            \Swift_Mailer::newInstance(\Swift_MailTransport::newInstance())
                ->send(\Swift_Message::newInstance()
                    ->setSubject(sprintf('Contact from %s', $_SERVER['SERVER_NAME']))
                    ->setFrom(array($data['email']))
                    ->setTo(array('s.rohweder@blage.com'))
                    ->setBody($data['message'])
                );

            return $app->redirect($app['url_generator']->generate(
                'contact_send'
            ));
        }
    }

    return $app['twig']->render('contact.twig', array(
        'form' => $form->createView(),
        'sent' => false
    ));  
  return $app['twig']->render('contact.twig');
})->bind('contact');

$app->get('contact/sent',function() use ($app){
  return $app['twig']->render('contact.twig',array('sent' => true));
})->bind('contact_send');

return $app;