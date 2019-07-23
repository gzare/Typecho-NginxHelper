<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 *  Typecho NginxHelper插件
 *  @package NginxHelper
 *  @author Zare
 *  @version 0.1 Beta
 *  @link https://www.ghl.name
 */
class nginxhelper_Plugin implements Typecho_Plugin_Interface
{

    /**
	* @global $IS_PURGE_ALL
	* 是否开启当变动时清除所有缓存
	*
	*/
	public static $IS_PURGE_ALL = false;

	/**
	* @global $NGINX_CACHE_PATH 
	* Nginx缓存目录，若宏NGINX_CACHE_PATH未定义，将默认/tmp/nginx目录
	* 目录最后请不要带/
	*/
	public static $NGINX_CACHE_PATH = '/tmp/nginx';

	/**
	* @global $AutoHitCache
	* 是否开启清除缓存后自动预缓存功能
	*/
	public static $AutoHitCache = false;
	
	/* 激活插件方法 */
public static function activate(){

	   //如果不支持Nginx fastcgi_purge插件，将会把缓存文件全部删除

	    Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish =   array('nginxhelper_Plugin','_PurgeCache');
	    Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishSave =   self::$IS_PURGE_ALL ? array('nginxhelper_Plugin', '_PurgeCache') : array('nginxhelper_Plugin','_Update');
	    Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishDelete =   array('nginxhelper_Plugin', '_PurgeCache');

	    //页面
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish =   array('nginxhelper_Plugin','_PurgeCache');
         Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishDelete =   array('nginxhelper_Plugin', '_PurgeCache');
          Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishEdit =   self::$IS_PURGE_ALL ? array('nginxhelper_Plugin', '_PurgeCache') : array('nginxhelper_Plugin','_Update');
        //评论
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('nginxhelper_Plugin', '_PurgeCache');
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array('nginxhelper_Plugin', '_PurgeCache');
         Typecho_Plugin::factory('Widget_Comments_Edit')->finishDelete = array('nginxhelper_Plugin', '_PurgeCache');
         Typecho_Plugin::factory('Widget_Comments_Edit')->finishEdit = array('nginxhelper_Plugin', '_PurgeCache');

        // Helper::addRoute('purgeall', '/purgeall/', 'PurgeALL_Action', 'action');
}
 
/* 禁用插件方法 */
public static function deactivate(){

	//没想到要写啥
	//Helper::removeRoute('purgeall');
	self::_PurgeCache();
}
 
/* 插件配置方法 */
public static function config(Typecho_Widget_Helper_Form $form){}
 




public static function personalConfig(Typecho_Widget_Helper_Form $form)
{
}

public static function _Update($content,$edit){
        if ('publish' !== $content['visibility'] ) {
            return;
        }
        $_Options  = Typecho_Widget::widget('Widget_Options');
         $routeExists = (NULL != Typecho_Router::get($content['type']));
		 $pathinfo = $routeExists ? Typecho_Router::url($content['type'], $content) : '#';
		 self::_DeleteURLCache(Typecho_Common::url($pathinfo, $_Options->index));


}
/*
问题代码
public static function _CommentUpdate(){
	//以下代码来自PHPGao
	$req = new Typecho_Request();
	$pathinfo = $req->getPathInfo();
	self::_DeleteURLCache( preg_replace('/\/comment$/i','',$pathinfo) );
}*/

public static function _PurgeCache(){

self::_Delete( self::$NGINX_CACHE_PATH );
//是否开启自动预缓存
if ( self::$AutoHitCache ) self::_AutoHitCache();
	

}

public static function _AutoHitCache(){
	$_DataBase = Typecho_Db::get();
	$_Options = Typecho_Widget::widget('Widget_Options');
	$_Page = $_DataBase->fetchAll($_DataBase->select()->from('table.contents')
		         ->where('table.contents.type = ?', 'page'));
    $_Post = $_DataBase->fetchAll($_DataBase->select()->from('table.contents')
                 ->where('table.contents.type = ?', 'post'));

                foreach ($_Page as $p) {
                
                	   $routeExists = (NULL != Typecho_Router::get($p['type']));
		           	$p['pathinfo'] = $routeExists ? Typecho_Router::url($p['type'], $p) : '#';
		           	$url = Typecho_Common::url($p['pathinfo'], $_Options->index);
		           	 if( ! file_exists(self::_GetURLCacheFile( $url )) ) self::_Hit( $url );
			         
			     }
                foreach ($_Post as $p) {
                	  $routeExists = (NULL != Typecho_Router::get($p['type']));
                	  $p['pathinfo'] = $routeExists ? Typecho_Router::url($p['type'], $p) : '#';
		           	$url = Typecho_Common::url($p['pathinfo'], $_Options->index);
		           	 if( ! file_exists(self::_GetURLCacheFile( $url )) ) self::_Hit( $url );
                }
          



 


}

public static function _Hit( $url ){
     if ( function_exists('curl_init') ){

     	   $_Curl = curl_init(); 
           curl_setopt($_Curl, CURLOPT_URL, $url); 
           //绕过一些基础WAF。如果你的WAF太强大，无能为力了，所以说，要给自己加白名单
           curl_setopt($_Curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36');
           curl_exec($_Curl); 
           curl_close($_Curl);  

     }else{
     	file_get_contents( $url );
     }
}
public static function _GetURLCacheFile( $url ){
	$url = parse_url($url);
    $scheme = $url['scheme'];
    $host = $url['host'];
    $requesturi = $url['path'];
    $hash = md5($scheme.'GET'.$host.$requesturi);
    return self::$NGINX_CACHE_PATH . '/'. substr($hash, -1) . '/' . substr($hash,-3,2) . '/' . $hash;
}

	/**
	 * Unlink Nginx URL Cache.
	 * Source - https://www.digitalocean.com/community/tutorials/how-to-setup-fastcgi-caching-with-nginx-on-your-vps
	 *
	 * @param string $url The url cache need to be deleted.
	 */

public static function _DeleteURLCache( $url ){
	$url = parse_url($url);
    $scheme = $url['scheme'];
    $host = $url['host'];
    $requesturi = $url['path'];
    $hash = md5($scheme.'GET'.$host.$requesturi);
    @unlink(self::$NGINX_CACHE_PATH .'/'. substr($hash, -1) . '/' . substr($hash,-3,2) . '/' . $hash);
}

	/**
	 * Unlink file recursively.
	 * Source - http://stackoverflow.com/a/1360437/156336
	 *
	 * @param string $dir Directory.
	 * @param bool   $_DeleteRoot Delete root or not.
	 */


public static function _Delete($dir,$_DeleteRoot=false){
			if( is_dir( $dir ) ) {


		if ( ! $_DirHook = opendir( $dir ) ) {
			return;
		}

		while ( false !== ( $_Object = readdir( $_DirHook ) ) ) {

			//有后缀不删。nginx缓存文件往往没有后缀

			if ( $_Object == '.' || $_Object == '..' ) {
				continue;
			}

	

			if ( ! @unlink( $dir . '/' . $_Object ) ) {
				self::_Delete( $dir .'/'.$_Object , true );
			}

		}
		
			closedir( $_DirHook );
			if ( $_DeleteRoot ) rmdir( $dir );
			return;

		}
		
		return;
	}
}



?>