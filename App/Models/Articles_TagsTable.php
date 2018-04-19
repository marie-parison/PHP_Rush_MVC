<?php

namespace App\Models;

use App\Models\TagsTable;
use App\Config\Db;

class Articles_TagsTable extends Table {

    protected $table = "articles_tags";

    public function associateTags($id_article, $tags){

        $tags_table = new TagsTable();
        $existing_tags = $tags_table->getAll();
        $previous_tags = self::getByArticleId($id_article);
        $tags = array_unique($tags);

        foreach($previous_tags as $previous_tag){
            if(!in_array($previous_tag["name"], $tags)){
                $id = $previous_tag["id"];
                parent::query("DELETE FROM $this->table WHERE id_article = {$id_article} AND id_tag = {$id}", [$id_article, $id]);
            }
        }
        
        foreach($tags as $tag){

            $existing = false;

            foreach($existing_tags as $existing_tag){
                if($tag == $existing_tag["name"]){
                    $existing = true;
                    $id_tag = $existing_tag["id"];
                }
            }

            if(!$existing){
                $tags_table->create(["name" => $tag]);
                $id_tag = Db::getLastInsertId();
            }

            $this->create(["id_article" => $id_article, "id_tag" => $id_tag]);
        }
    }

    //Récupére tous les tags associés à un article
    public function getByArticleId($id){

        $tags_table = new TagsTable();

        $tags = [];
        $result = parent::query("SELECT id_tag FROM articles_tags WHERE id_article = {$id}", [$id]);
        
        foreach($result as $tag){
            $tags[] = $tags_table->getById($tag["id_tag"]);
        }
        return $tags;
    }
}