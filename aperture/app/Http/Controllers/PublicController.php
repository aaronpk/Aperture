<?php
namespace App\Http\Controllers;

use Request, Response, DB;

class PublicController extends Controller
{

  public function docs() {
    return view('docs');
  }

  public function pricing() {
    return view('pricing');
  }

}
