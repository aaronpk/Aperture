<?php

use Faker\Generator as Faker;

$factory->define(App\Entry::class, function(Faker $faker) {

  $domain = $faker->domainName;
  $baseURL = 'https://'.$faker->domainName.'/';

  $uid = $baseURL.'?'.$faker->numberBetween(100,999);

  $data = json_encode([
    "type" => "entry",
    "author" => [
      "name" => $faker->firstName.' '.$faker->lastName,
      "url" => $baseURL,
      "photo" => null
    ],
    "uid" => $uid,
    "url" => $baseURL.$faker->slug,
    "published" => "2016-12-16T15:18:09+00:00",
    "name" => $faker->words(3, true),
    "content" => [
        "html" => "<p>".$faker->sentence(12)."</p>",
        "text" => $faker->sentence(12)
    ]
  ], JSON_UNESCAPED_SLASHES+JSON_PRETTY_PRINT);

  return [
    'data' => $data,
    'unique' => $uid,
  ];
});
