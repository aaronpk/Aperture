<?php
namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Podcast;

class HomeController extends Controller
{
    public function __construct() {
        $this->middleware('auth');
    }

    public function index() {
        return view('dashboard', [
        ]);
    }
}

