<?php

namespace Bolt\Extension\SalidaSoftware\BoltContact;

use Bolt\Application;
use Bolt\BaseExtension;
use Bolt\Users;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Bolt\Translation\Translator as Trans;

class Extension extends BaseExtension
{
    // These store some session info for middleware "after" callback:
    private $contact_success = NULL;
    private $contact_invalid_form = NULL;

    // Whether to inject the contact form into the page or not
    public $active = false;

    /**
     * Bolt extension initialize
     */
    public function initialize() {
    	$this->addCss('assets/extension.css');

        // We don't need custom javascript (yet)
        //$this->addJavascript('assets/start.js', true);\\

        $extension = $this;
        
        //Add a Silex route for handling contact form submissions
        $this->app->post($this->submitUrl(), function(Request $request) use ($extension) { 
		    return $extension->handleForm($request); 
		});

		$this->app->before(function (Request $request, Application $app) use ($extension) {
		    $uri = $request->getRequestUri();
		    
		    //Don't be active on routes that have "editcontent" in them.  We don't want to affect the bolt backend.
			if(strstr($uri, 'editcontent') === FALSE) {
				$extension->active = true;
			}
	    	
		});

        //Session behaves weirdly inside the following "after" middleware, so capture needed session info here.
		$this->contact_success = $this->getFlash('contact_success');
		$this->contact_invalid_form = $this->getFlash('contact_invalid_form');

        //Add a Silex middleware to inject contact form into content
		$this->app->after(function (Request $request, Response $response) use ($extension) { 
			if($extension->active) {
	    		$content = $response->getContent();
	    		$content = str_replace("[contact]", $extension->renderForm(), $content);
	    		$response->setContent($content);
    		}
		});

    }

    /**
     * Store a session flash value
     */
    public function setFlash($key, $val) {
    	$this->app['session']->set($key, $val); 
    }

    /**
     * Fetch a session flash value
     * 
     * @return mixed
     */
    public function getFlash($key) {
    	if($this->app['session']->has($key)) {
    		$value = $this->app['session']->get($key);
    		$this->app['session']->remove($key);
    		return $value;
    	}
    }

    /**
     * Bolt extension name
     * 
     * @return string
     */
    public function getName()
    {
        return "boltcontact";
    }

    /**
     * Determine the url to use for the contact form's action attribute
     * 
     * @return string
     */
    public function submitUrl() {
    	return $this->getBaseUrl().'submit';
    }

    /**
     * Generate the form builder
     *
     * @return Symfony\Component\Form\FormBuilder
     */
    public function buildForm() {
    	$form = $this->app['form.factory']->createBuilder('form', array())
	        ->add('name', 'text', array(
        		'constraints' => array(new Assert\NotBlank())
   			))
	        ->add('email', 'text', array(
        		'constraints' => new Assert\Email()
    		))
    		->add('last_name', 'text', array(  //honeypot
    			'required' => false,
    			'constraints' => array(new Assert\Blank())
    		))
	        ->add('message', 'textarea', array(
	        	'constraints' => array(new Assert\NotBlank())
	        ))
        	->getForm();
        return $form;
    }

    /**
     * Renders the contact form's raw html
     *
     * @return string
     */
    public function renderForm($view = NULL) {
    	if(!$view) {
    		$view = $this->buildForm()->createView();
    	}
    	$this->app['twig.loader.filesystem']->addPath($this->getBasePath(), 'contact');
    	$success = $this->contact_success;
    	$invalid_form = $this->contact_invalid_form;
    	$form_html = $this->app['twig']->render('@contact/form.twig', array(
    		'success' => $success,
    		'invalid_form' => $invalid_form,
    		'form' => $view,
    		'action' => $this->submitUrl(),
    	));
    	
    	return $form_html;
    }

    /**
     * Handles form submissions, and redirects back to original form page
     *
     * @return Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleForm(Request $request) {
    	$form = $this->buildForm();
    	$form->handleRequest($request);

	    if ($form->isValid()) {
	        $data = $form->getData();

	        $to = $this->config['to'];
	        if(!$to) {
	        	$user = $this->app['users']->getUser(1);
	            $to = array($user['email']);
	    	}
	        $subject = sprintf("[%s] Contact Form Submission.", $this->app['config']->get('general/sitename'));

	        // Send the email message
	        $message = \Swift_Message::newInstance()
		        ->setSubject($subject)
		        ->setFrom(array($data['email'] => $data['name']))
		        ->setTo($to)
		        ->setBody($data['message']);

	    	$this->app['mailer']->send($message);

	        $this->setFlash('contact_success', Trans::__("Thank you! Your message has been delivered."));
	    }
	    else {

	    	// If the form is not valid, render it with errors and pack it into session since we have to do a redirect regardless
	    	$this->setFlash('contact_invalid_form', $this->renderForm($form->createView()));
	    }

	    return $this->app->redirect($request->server->get('HTTP_REFERER'));

    }

}
