<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Doge;
use App\Models\Queue;
use App\Models\Subscribe;
use App\Models\Trading;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DogeController extends Controller
{
  /**
   * @return Application|Factory|View
   */
  public function index()
  {
    $user = User::find(Auth::id());
    $data = [
      "user" => $user
    ];
    return view("doge.bet", $data);
  }

  /**
   * @param string $type
   * @param string $target
   * @param null $next
   * @param null $cookieUser
   * @return Application|Factory|View|RedirectResponse
   */
  public function history(string $type = "income", string $target = "internal", $next = null, $cookieUser = null)
  {
    if ($type == "income") {
      $action = "GetDeposits";
    } else {
      $action = "GetWithdrawals";
    }

    if ($cookieUser) {
      $cookie = $cookieUser;
    } else {
      $cookie = Auth::user()->doge->cookie;
    }

    $post = HttpController::post($action, [
      "s" => $cookie,
      "Token" => $next != "-" ? $next : ""
    ]);

    if ($post->code < 400) {
      $next = $post->data->Token;
      if ($target == "internal") {
        $lists = $post->data->Transfers;
      } else if ($type == "income") {
        $lists = $post->data->Deposits;
      } else {
        $lists = $post->data->Withdrawals;
      }

      $data = [
        "type" => $type,
        "target" => $target,
        "next" => $next,
        "cookie" => $cookie,
        "lists" => $lists
      ];

      return view("history.ledger", $data);
    }

    return redirect()->back()->with(["warning" => $post->message]);
  }

  /**
   * @param Request $request
   * @param $type
   * @param bool $isAll
   * @return RedirectResponse
   * @throws ValidationException
   */
  public function transfer(Request $request, $type, bool $isAll = false): RedirectResponse
  {
    $doge = Doge::where("user_id", Auth::id())->first();
    $bot = Trading::where("user_id", Auth::id())->first();
    if ($type == "bot") {
      if ($isAll) {
        $post = self::withdraw($doge->cookie, $bot->wallet, 0);
      } else {
        $this->validate($request, [
          "amount" => "required|numeric|min:2",
        ]);
        $post = self::withdraw($doge->cookie, $bot->wallet, round($request->amount * 10 ** 8));
      }
    } else if ($isAll) {
      $post = self::withdraw($bot->cookie, $doge->wallet, 0);
    } else {
      $this->validate($request, [
        "amount" => "required|numeric|min:2",
      ]);
      $post = self::withdraw($bot->cookie, $doge->wallet, round($request->amount * 10 ** 8));
    }

    if ($post->code < 400) {
      return redirect()->back()->with(["message" => $post->message]);
    }

    return redirect()->back()->with(["warning" => $post->message]);
  }

  /**
   * @param Request $request
   * @return array
   * @throws ValidationException
   */
  public function store(Request $request): array
  {
    $subscribe = Subscribe::where("user_id", Auth::id())->where("is_finished", false)->where("expired_at", ">=", Carbon::now())->count();
    if (!$subscribe) {
      return [
        "code" => 500,
        "message" => "please subscribe or top up your balance",
      ];
    }
    $bot = Trading::where("user_id", Auth::id())->first();
    $this->validate($request, [
      "high" => "required|numeric|min:5|max:99",
      "bet" => "required|numeric|min:0.00000001",
    ]);
    $post = HttpController::post("PlaceBet", [
      "s" => $bot->cookie,
      "PayIn" => (integer)round($request->input("bet") * 10 ** 8),
      "Low" => 0,
      "High" => (integer)round($request->input("high") * 10 ** 4),
      "ClientSeed" => mt_rand(),
      "Currency" => "doge",
      "ProtocolVersion" => 2,
    ]);
    if ($post->code < 400) {
      $payIn = round($request->input("bet") * 10 ** 8);
      $payOut = $post->data->PayOut;
      $profit = $payOut - $payIn;
      $betBalance = $post->data->StartingBalance;
      $profitBalance = $betBalance + $profit;
      return [
        "code" => 200,
        "message" => $post->message,
        "payIn" => $payIn,
        "payOut" => $payOut,
        "betBalance" => $betBalance,
        "profit" => $profit,
        "profitBalance" => round($profitBalance / 10 ** 8, 8),
      ];
    }

    return [
      "code" => 500,
      "message" => $post->message,
    ];
  }

  /**
   * @return Application|Factory|View
   */
  public function createWithdraw()
  {
    return view("doge.withdraw");
  }

  /**
   * @param Request $request
   * @return RedirectResponse
   * @throws ValidationException
   */
  public function storeWithdraw(Request $request): RedirectResponse
  {
    $this->validate($request, [
      "amount" => "required|numeric|min:10",
      "wallet" => "required|string"
    ]);
    $doge = Doge::where("user_id", Auth::id())->first();
    if (!$doge->cookie) {
      $login = self::login($doge->username, $doge->password);
      if ($login < 400) {
        $doge->cookie = $login->data->cookie;
        $doge->save();
      } else {
        return redirect()->back()->with(["warning" => $login->message]);
      }
    }
    $validateWalletDoge = Doge::where("wallet", $request->input("wallet"))->count();
    $validateWalletBot = Trading::where("wallet", $request->input("wallet"))->count();
    $fee = 0;
    if (!$validateWalletDoge && !$validateWalletBot) {
      if ($request->input("amount") > 500) {
        $fee = $request->input("amount") * 0.02;
      } else {
        $fee = 5;
      }
    }
    $withdraw = self::withdraw($doge->cookie, $request->input("wallet"), round(($request->input("amount") - $fee) * 10 ** 8));
    if ($withdraw->code < 400) {
      if ($fee != 0) {
        $withdrawShare = self::withdraw($doge->cookie, Bank::first()->wallet, round($fee * (10 ** 8)));
        if ($withdrawShare->code < 400) {
          self::share(Auth::id(), round($fee * (10 ** 8)));
        }
      }

      return redirect()->back()->with(["message" => $withdraw->message]);
    }

    return redirect()->back()->with(["warning" => $withdraw->message]);
  }

  /**
   * @return object
   */
  public static function createAccount(): object
  {
    $post = HttpController::post("CreateAccount", [], true);
    if ($post->code < 400) {
      return (object)[
        "code" => 200,
        "message" => "successfully created account",
        "data" => (object)[
          "cookie" => $post->data->SessionCookie
        ],
      ];
    }

    return (object)[
      "code" => 400,
      "message" => "create account failed. please try again",
      "data" => [],
    ];
  }

  /**
   * @param $cookie
   * @return object
   */
  public static function wallet($cookie): object
  {
    $data = [
      "s" => $cookie,
      "Currency" => "doge"
    ];
    $post = HttpController::post("GetDepositAddress", $data);
    if ($post->code < 400) {
      return (object)[
        "code" => $post->code,
        "message" => $post->message,
        "data" => (object)[
          "wallet" => $post->data->Address
        ],
      ];
    }

    return (object)[
      "code" => $post->code,
      "message" => $post->message,
      "data" => [],
    ];
  }

  /**
   * @param $cookie
   * @param $username
   * @param $password
   * @return object
   */
  public static function createUser($cookie, $username, $password): object
  {
    $data = [
      "s" => $cookie,
      "Username" => $username,
      "Password" => $password,
    ];
    $post = HttpController::post("CreateUser", $data);
    return (object)[
      "code" => $post->code,
      "message" => $post->message,
    ];

  }


  /**
   * @param $username
   * @param $password
   * @return object
   */
  public static function login($username, $password): object
  {
    $data = [
      "Username" => $username,
      "Password" => $password,
    ];
    $post = HttpController::post("Login", $data, true);
    if ($post->code < 400) {
      return (object)[
        "code" => $post->code,
        "message" => $post->message,
        "data" => (object)[
          "cookie" => $post->data->SessionCookie,
          "balance" => $post->data->Doge["Balance"],
        ],
      ];
    }

    return (object)[
      "code" => $post->code,
      "message" => $post->message,
      "data" => []
    ];
  }

  /**
   * @param $cookie
   * @return object
   */
  public static function balance($cookie): object
  {
    $data = [
      "s" => $cookie,
      "Currency" => "doge"
    ];
    $post = HttpController::post("GetBalance", $data);
    if ($post->code < 400) {
      return (object)[
        "code" => $post->code,
        "message" => $post->message,
        "data" => (object)[
          "balance" => $post->data->Balance,
        ],
      ];
    }

    return (object)[
      "code" => $post->code,
      "message" => $post->message,
      "data" => [],
    ];
  }

  /**
   * @param $cookie
   * @param $wallet
   * @param $amount
   * @return object
   */
  public static function withdraw($cookie, $wallet, $amount): object
  {
    $data = [
      "s" => $cookie,
      "Address" => $wallet,
      "Amount" => (integer)$amount,
      "Currency" => "doge",
    ];
    $post = HttpController::post("Withdraw", $data);
    return (object)[
      "code" => $post->code,
      "message" => $post->message,
      "data" => [],
    ];

  }

  /**
   * @return string
   */
  public function url(): string
  {
    return "https://www.999doge.com/api/web.aspx";
  }

  /**
   * @param int $length
   * @return string
   * @throws Exception
   */
  public static function randomAccount(int $length = 20): string
  {
    $characters = "0123456789IJOOLijool";
    $charactersLength = strlen($characters);
    $randomString = "";
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return "IijoolI" . $randomString;
  }

  public static function share($sender, $amount)
  {
    $value = $amount / 2;

    $bankQueue = new Queue();
    $bankQueue->from = $sender;
    $bankQueue->to = 1;
    $bankQueue->value = $value;
    $bankQueue->save();

    $bankQueue = new Queue();
    $bankQueue->from = $sender;
    $bankQueue->to = 2;
    $bankQueue->value = $value;
    $bankQueue->save();

    Log::info("Withdraw=========================");
    Log::info("amount : $amount");
    Log::info("sender : $sender | target : [1, 2] | value : $value");
    Log::info("Withdraw=========================End");
  }
}
