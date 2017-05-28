<?php
/**
 * Created by PhpStorm.
 * @ Author: code lighter
 * @ Date: 2017/5/28
 * @ Time: 11:33
 */
namespace company\controllers;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use company\code_lighter\MenuTree;

class TestController extends Controller
{
    public $enableCsrfValidation = false;
    public function actionMenuTree()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $menuTree = new MenuTree("menu.txt");
        return $menuTree->toJson();
    }
}
