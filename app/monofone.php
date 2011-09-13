<?php

include(__DIR__.'/vendor/Silex/autoload.php');

use Silex\Extension\TwigExtension;
use Symfony\Component\HttpFoundation\Response;

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
    $request = $app['request'];
    /*@var $request Symfony\Component\HttpFoundation\Request*/
    $userAgent = $request->headers->get('user-agent');
    if(stristr($request->headers->get('user-agent'),'Android')){
      $app['twig']->addGlobal('isMobile',true);
    }
    
});
 
$app->error(function($error) use($app) {
    if(preg_match("/found|find/",$error->getMessage())){
      return new Response($app['twig']->render('404.twig'),'404');
    }else{
      return new Response($app['twig']->render('error.twig'),'500');
    }
});
 
$app->get('/', function() use ($app) {
  $url = $app['url_generator']->generate('page', array('name'=>'vita'));
  return $app->redirect($url);
});

$app->get('/page/{name}', function($name) use ($app) {
  if(file_exists(__DIR__.'/views/'.$name.'.twig')){
    return $app['twig']->render($name.'.twig');  
  }else{
    return new Response($app['twig']->render('404.twig'),'404');
  }
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