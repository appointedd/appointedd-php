#Appointedd PHP Client


##Getting Started

###Composer

Install the composer PHP package

Add the following to your composer.json require section:

    "appointedd/appointedd-php": "dev-master"

and run ```composer update```

###Manually

It's recommended that you use Composer, however you can download and install from this repository.

Please note. This client requires the Guzzle REST PHP package.

###Laravel 4

This package comes with a Service Provider for easy integration with Laravel4.

Add the following entry to the providers array in config/app.php

	'Appointedd\Appointedd\AppointeddServiceProvider'


##Usage

	$apClientWToken = Appointedd::setAccessToken('gTHZNc7DVZJI24KIcFLHTipMIqUWFSrA');
	$customers = $apClientWToken->get('salon/staff', array('query'=>Input::get('query', '')));
	if(is_object($customers))
		var_dump($customers->json());

##Endpoints

TODO