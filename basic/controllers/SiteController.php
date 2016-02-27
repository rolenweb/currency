<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\UsdRate;
use app\models\EurRate;

class SiteController extends Controller
{

    const HISTORY_DATE_CURRENCY_RATE = "2000-01-01";

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        set_time_limit(600000);

        
        //$this->updateCurrencyRate('USD');
        $this->updateCurrencyRate('EUR');
        
        //return $this->render('index');
    }

    public function actionLogin()
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    public function updateCurrencyRate($currnecy)
    {
        if ($currnecy == 'USD') {
            $last_date = UsdRate::find()->orderBy(['date' => SORT_DESC])->limit(1)->one();
        }
        if ($currnecy == 'EUR') {
            $last_date = EurRate::find()->orderBy(['date' => SORT_DESC])->limit(1)->one();
        }
        
        if ($last_date != NULL) {
            $start_date = date("Y-m-d",strtotime("+1 day", strtotime($last_date->date)));
        }
        else{
            $start_date = self::HISTORY_DATE_CURRENCY_RATE;
        }
        $n=0;
        while ($start_date <= date("Y-m-d")) {
            $rate = $this->loadCurrencyRates($currnecy, $start_date);

            if ($rate != NULL) {
                foreach ($rate->rates as $key => $value) {
                    if ($currnecy == 'USD') {
                        $new_rec = new UsdRate();
                    }
                    if ($currnecy == 'EUR') {
                        $new_rec = new EurRate();
                    }

                    $new_rec->date = $start_date;
                    $new_rec->code = $key;
                    $new_rec->rate = $value;
                    $new_rec->save();
                            
                }
            }
            $start_date = date("Y-m-d", strtotime("+1 day", strtotime($start_date)));
            if ($n == 1000) {
                die();
            }
            $n++;
        }
    }

    public function loadCurrencyRates($base, $date)
    {
        $our = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://api.fixer.io/latest?base=".$base."&date=".$date);
        //curl_setopt($ch, CURLOPT_URL, "http://api.fixer.io/".$date);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        $response = curl_exec($ch);
        curl_close($ch);
        $out = json_decode($response);
        return $out;
    }
}
