<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationNotificationController extends Controller
{
  /**
   * Send a new email verification notification.
   *
   * @param Request $request
   * @return RedirectResponse|Response
   */
  public function store(Request $request)
  {
    if ($request->user()->hasVerifiedEmail()) {
      return redirect()->intended(RouteServiceProvider::HOME);
    }

    $request->user()->sendEmailVerificationNotification();

    return back()->with("status", "verification-link-sent");
  }
}
