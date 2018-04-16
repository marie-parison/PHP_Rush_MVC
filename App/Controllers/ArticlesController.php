<?php

namespace App\Controllers;

use App\Config\Db;
use App\Models\ArticlesTable;
use App\Models\CategoriesTable;
use App\Models\CommentsTable;
use App\Models\TagsTable;
use App\Models\Articles_TagsTable;
use App\Src\Request;
use App\Src\User;
use App\Src\UserRight;

class ArticlesController extends Controller
{
    public static function displayAllAction(Request $request)
    {
        $errors = [];
        $articles = [];
        $comments = [];
        $article_table = new ArticlesTable();
        $tags_models = new TagsTable();
        $post = $request->getMethodParams();

        if (empty($post) || (empty($post["text"]) && !$post["tag"] && $post["category"] == "all")) {
            $articles = $article_table->getAll();
        } else {
            $post = parent::secureDataArray($post);
            $articles = $article_table->getFiltered($post);     
        }

        if (!$articles) {
            $errors["articles"] = "No articles found";
        } else {

            $comments_table = new CommentsTable();

            foreach ($articles as $key => $article) {
                $articles[$key]['path_image'] = $article['path_image'];
                $articles[$key]['creation_date'] = parent::dateFormat($article['creation_date']);
                $articles[$key]['tags'] = $tags_models->getByArticleId($article['id']);
            }

            $comments_table = new CommentsTable();
            $comments = $comments_table->getByArticleId($article['id']);
            foreach ($comments as $key => $comment) {
                $comments[$key]['creation_date'] = parent::dateFormat($comment['creation_date']);
            }
        }

        // Recuperation des categories en passant par le MODEL
        $categories_models = new CategoriesTable();
        $categories = $categories_models->getByDesc();

        $tags = $tags_models->getAll();

        parent::render('/Articles/articles.html.twig', [
            "articles" => $articles,
            "search" => ["categories" => $categories, "tags" => $tags],
            "errors" => $errors,
        ]);
    }

    public static function displayAction(Request $request)
    {
        $id = $request->getParams()["id"];
        $article_table = new ArticlesTable();
        $comments_table = new CommentsTable();
        $categories_table = new CategoriesTable();
        $tags_models = new Articles_TagsTable();

        // 1. RÉCUPÉRER LES DONNÉES DE L'ARTICLE
        $article = $article_table->getById($id);
        if ($article) {
            $no_article = false;
            if ($request->getMethod() == "POST") {
                $new_comment = [];
                $new_comment['id_writer'] = User::getInstance()->getId();
                $new_comment['id_article'] = $article['id'];
                $new_comment['content'] = $request->getMethodParams()['content'];
                $new_comment['creation_date'] = date("Y-m-d H:i:s");
                if (!empty($new_comment['content'])) {
                    $comments_table->create($new_comment);
                }
            }
            $article['path_image'] = $article['path_image'];
            $article['creation_date'] = parent::dateFormat($article['creation_date']);
            // 2. RÉCUPÉRER LES COMMENTAIRES
            $comments = $comments_table->getByArticleId($article['id']);
            foreach ($comments as $key => $comment) {
                $comments[$key]['creation_date'] = parent::dateFormat($comment['creation_date']);
            }
            // 3. RÉCUPÉRER LES TAGS
            $tags = $tags_models->getByArticleId($article['id']);
            parent::render('/Articles/article.html.twig', [
                "no_article" => $no_article,
                "article" => $article,
                "comments" => $comments,
                "tags" => $tags,
            ]);
        } else {
            $no_article = true;
            parent::render('/Articles/article.html.twig', ["no_article" => $no_article]);
        }
    }

    public static function createAction(Request $request)
    {   
        $article_table = new ArticlesTable();
        $categories_table = new CategoriesTable();
        $tags_table = new TagsTable();

        //SI L'UTILISATEUR A DÉJÀ CLIQUÉ SUR Create, GÉRER LA CRÉATION DE L'ARTICLE
        if ($request->getMethod() == "POST") {
            //1. Récupérer l'article + catégorie
            $new_article = $request->getMethodParams();
            //2. Récupérer l'image
            if ($request->getFiles()["path_image"]["tmp_name"]) {
                $fileImg = $request->getFiles()["path_image"]["tmp_name"];
            } else {
                unset($new_article["path_image"]);
            }
            if ($fileImg) {
                $filePath = self::saveUploadFile($fileImg, "article_img");
                $new_article["path_image"] = $filePath;
            }

            if(!empty($new_article["tags"])){
                $tags = explode(",", $new_article["tags"]);
            }
            unset($new_article["tags"]);
            //3. Vérifier si les champs Title et Content ne sont pas vides avant de créer l'article
            $title = $new_article["title"];
            $content = $new_article["content"];
            
            if (!empty($title) && !empty($content)) {
                $new_article['id_writer'] = User::getInstance()->getId();
                $new_article['creation_date'] = date("Y-m-d H:i:s");
                //4. Contrôler si la création de l'article a réussi
                if ($article_table->create($new_article)) {
                    if($tags){
                        $new_article["id"] = Db::getLastInsertId();
                        $articles_tags_table = new Articles_TagsTable();
                        $tags = parent::secureDataArray($tags);
                        //TODO Vérifier retour création tags
                        $articles_tags_table->associateTags($new_article["id"], $tags);
                    }              
                $result = "Creation successfull";
                } else {
                    $result = "Error during the creation :(";
                }
            } else {
                $result = "You must fill all fields";
            }
            echo $result;
        } else {
            //POUR PERMETTRE LE CHOIX DE CATÉGORIE AU MOMENT DE LA CRÉATION DE L'ARTICLE, renvoyer la liste des catégories
            $categories = $categories_table->getAll();
            $tags = $tags_table->getAll();
            parent::render('/Articles/create.html.twig', [
                "categories" => $categories,
                "tags" => $tags,
            ]);
        }
    }

    public static function editAction(Request $request)
    {
        $id = $request->getParams()["id"];
        $article_table = new ArticlesTable();
        $categories_table = new CategoriesTable();
        $tags_table = new TagsTable();
        $articles_tags_table = new Articles_TagsTable();

        //SI L'UTILISATEUR A DÉJÀ CLIQUÉ SUR Edit, GÉRER L'ÉDITION DE L'ARTICLE
        if ($request->getMethod() == "POST") {
            //1. Récupérer l'article + catégorie
            $edit_article = $request->getMethodParams();
            //2. Récupérer l'image
            if ($request->getFiles()["path_image"]["tmp_name"]) {
                $fileImg = $request->getFiles()["path_image"]["tmp_name"];
            } else {
                unset($edit_article["path_image"]);
            }
            if ($fileImg) {
                $filePath = self::saveUploadFile($fileImg, "article_img");
                $edit_article["path_image"] = $filePath;
            }

            if(!empty($edit_article["tags"])){
                $tags = explode(",", $edit_article["tags"]);
            }
            unset($edit_article["tags"]);
            //3. Vérifier si les champs Title et Content ne sont pas vides avant d'éditer l'article
            $title = $edit_article["title"];
            $content = $edit_article["content"];
            if (!empty($title) && !empty($content)) {
                $edit_article['edition_date'] = date("Y-m-d H:i:s");
                //4. Contrôler si l'édition de l'article a réussi
                if ($article_table->modifyById($id, $edit_article)) {
                    $articles_tags_table = new Articles_TagsTable();
                    $tags = parent::secureDataArray($tags);
                    //TODO Vérifier retour création tags
                    $articles_tags_table->associateTags($id, $tags);
                    $result = "Edition successfull";
                } else {
                    $result = "Error during the edition :(";
                }
            } else {
                $result = "You must fill all fields";
            }
            echo $result;
        } else {
            //PAR DÉFAUT, AFFICHER LES DONNÉES DE L'ARTICLE QUE L'ON SOUHAITE ÉDITER
            $article = $article_table->getById($id);
            $categories = $categories_table->getAll();
            $article_tags = $articles_tags_table->getByArticleId($id);
            //TODO Récupérer tous les tags sauf ceux de l'article en question
            $tags = $tags_table->getbyParams(["id"]);
            parent::render('/Articles/edit.html.twig', [
                "article" => $article,
                "categories" => $categories,
                "tags" => $tags,
                "article_tags" => $article_tags,
            ]);
        }
    }

    // Ceci est un controller pour de l'AJAX uniquement
    public static function deleteAction(Request $request)
    {

        if ($request->getMethod() === "DELETE") {
            $data = $request->getMethodParams();
            $id = $data["id"];
            if (empty($id)) {
                self::sendJsonErrorAndDie("Id is empty!");
            }

            $articleModel = new ArticlesTable();
            // On check si l'article existe bien
            $articleData = $articleModel->getById($id);
            if (!$articleData) {
                self::sendJsonErrorAndDie("Article does not exist!");
            }

            $user = User::getInstance();
            // On check si l'utilisateur a les droits pour supprimer l'article
            if ($user->getRight() >= UserRight::ADMIN ||
                ($user->getRight() === UserRight::WRITER && $articleData["id_writer"] === $user->getId())) {
                $articleModel->deleteById($id);
                self::sendJsonDataAndDie(["success" => true]);
            } else {
                self::sendJsonErrorAndDie("You don't have right to delete this article!");
            }
        } else {
            self::sendJsonErrorAndDie("Steven, is it you??");
        }
    }
}
