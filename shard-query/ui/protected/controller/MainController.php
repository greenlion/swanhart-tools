<?php
/**
 * MainController
 * Feel free to delete the methods and replace them with your own code.
 *
 * @author darkredz
 */
class MainController extends DooController{

    public function index(){
		//Just replace these
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::displayHome();
    }
	
	public function allurl(){	
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::showAllUrl();	
	}
	
    public function debug(){
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::showDebug($this->params['filename']);
    }
	
	public function gen_sitemap_controller(){
		//This will replace the routes.conf.php file
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::buildSitemap(true);		
		DooSiteMagic::buildSite();
	}
	
	public function gen_sitemap(){
		//This will write a new file,  routes2.conf.php file
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::buildSitemap();		
	}
	
	public function gen_site(){
		Doo::loadCore('app/DooSiteMagic');
		DooSiteMagic::buildSite();
	}
	
    public function gen_model(){
        Doo::loadCore('db/DooModelGen');
        DooModelGen::genMySQL();
    }

}
?>