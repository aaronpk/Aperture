<?php

use Faker\Generator as Faker;

$factory->define(App\Source::class, function(Faker $faker) {
    return [
      'token' => str_random(32),
      'url' => 'https://'.$faker->domainName.'/',
      'format' => 'microformats',
      'created_by' => App\User::first(),
    ];
});
