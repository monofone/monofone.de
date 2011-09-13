<?php

include(__DIR__.'/vendor/Silex/autoload.php');

use Silex\Extension\TwigExtension;
use Symfony\Component\HttpFoundation\Response;
use Silex\Extension\MonologExtension;
$app = new Silex\Application();

$app['debug'] = false;

$app['autoloader']->registerNamespace('Doctrine\\DBAL', __DIR__.'/vendor/doctrine-dbal/lib');

$app['autoloader']->registerNamespace('Doctrine\\Common', __DIR__.'/vendor/doctrine-common/lib'); 
$app['autoloader']->registerNamespace('Monolog', __DIR__.'/vendor/monolog/src'); 

$app->register(new MonologExtension(), array(
    'monolog.class_path'    => __DIR__.'/vendor/monolog/src',
    'monolog.logfile'       => __DIR__.'/var/development.log',    
    'monolog.name'          => 'cars',
//    'monolog.level'         => Logger::INFO,
));


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

$app->register(new Silex\Extension\DoctrineExtension(), array(
    'db.options' => array(
        'driver' => 'pdo_sqlite',
        'path' => __DIR__.'/var/storage.sqlite'
    ),
    'db.dbal.class_path'    => __DIR__.'/vendor/doctrine-dbal/lib',
    'db.common.class_path'  => __DIR__.'/vendor/doctrine-common/lib',
));

$app->register(new Silex\Extension\UrlGeneratorExtension());
 
$app->before(function() use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});
 
$app->error(function($error) use($app) {
  if(preg_match("/found|find",$error.getMessage())){
    $status = '404';
  }else{
    $status = '500';
  }
  return new Response($app['twig']->render('error.twig', array('error' => $error)),$status);
});
 
$app->get('/', function() use ($app) {
  $url = $app['url_generator']->generate('page', array('name'=>'vita'));
  return $app->redirect($url);
});

$app->get('/page/{name}', function($name) use ($app) {
  return $app['twig']->render($name.'.twig');
})->bind('page');

$app->match('/service/volumereader', function() use($app){

  $form = $app['form.factory']
      ->createBuilder('form', array('csrf_protection' => false, 'csrf_provider' => null))
          ->add('image', 'file', array('required' => false,'label' => 'Bild'))
          ->add('kdnumber', 'text')
          ->getForm();
    $app['monolog']->debug("Before bind");
    $form->bindRequest($app['request']);
    if($form->isValid()){
      $app['monolog']->debug("Form is valid");
      
      $imageFile = $form['image']->getData();
      
      if($imageFile){
        /* @var $imageFile Symfony\Component\HttpFoundation\File\UploadedFile */
        $extension = $imageFile->guessExtension();
        /* @var $form \Symfony\Component\Form\Extension\Core\Type\FieldType */
        if(!$extension){
          $extension = 'bin';
        }
        $filename = 'kdn_'.$form['kdnumber']->getData().'_'.time().'.'.$extension;
        $imageFile->move(__DIR__.'/var/upload', $filename);
      }
      $insertSql = 'INSERT INTO volumereader (kdnumber,img_name) VALUES (\''.$form['kdnumber']->getData().'\',\''.$filename.'\')';
      try{
        $app['db']->executeQuery($insertSql);
      }catch (Exception $e){
        $app['monolog']->debug($e->getMessage()."Booo");
        $response = new Response($e->getMessage(), 400);
      }
      $response = new Response('',201);
    }else{

      $app['monolog']->debug(print_r($form->getErrors(),true));
      $response = new Response('Data not valid',400);
    }
    
  return $response;
  
})->bind('volumereader');

$app->match('/servicetest', function() use ($app){
  
  $sent = false;
  
  $app['form.csrf_provider'] = null;
  
  $form = $app['form.factory']
      ->createBuilder('form')
          ->add('image', 'file', array('label' => 'Bild'))
          ->add('kdnumber', 'text')
          ->getForm();
  
  
  
  return $app['twig']->render('servicetest.twig',array(
      'form' => $form->createView(),
      'sent' => $sent
      ));
})->bind('servicetest');

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