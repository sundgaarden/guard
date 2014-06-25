<?php

$last_time = $server_time = time();
$expiry_date = $last_time + (45 * 60);

$req_img_width=300;
$req_img_height=250;

if (!class_exists('S3')) require_once 'S3.php';
if (!defined('awsAccessKey')) define('awsAccessKey', '');
if (!defined('awsSecretKey')) define('awsSecretKey', '');

$s3 			= new S3(awsAccessKey, awsSecretKey);
$bucketName 	= "theguardian-rss-dev";

$rss_array = array(
					array("http://www.theguardian.com/sport/rss","sport"),
					array("http://www.theguardian.com/music/rss","music"),
  					array("http://www.theguardian.com/news/rss","news"),
				array("http://www.theguardian.com/technology/rss","technology"),
			 	);

$contents = $s3->getBucket($bucketName);
//$a = ysa_clear_bucket($s3,$bucketName,$contents);

for($r=0; $r < count($rss_array);$r++)
{
	$server_img_path = "http://".$bucketName.".s3.amazonaws.com/";
	$server_img_path = "http://d201fgj80qubt4.cloudfront.net/";
	$url = $rss_array[$r][0];
	$category = $rss_array[$r][1];
	//$cat_img_dir = $category."/images/".$server_time."/images/";//$category."/images/";
	$img_path_cf = "http://d201fgj80qubt4.cloudfront.net/".$category."/images/".$server_time."/images/";
	
	$cat_img_dir = $category."/images/";//$cat_img_dir;
	$local_cat_img_dir = $category."/images/".$server_time."/images/";
	$xml_file_name = $category.".xml";		
	$img_path = $cat_img_dir;
	
	array_map('unlink', glob($cat_img_dir."/*.*"));
	array_map('unlink', glob($category."/*.*"));

	if (!file_exists($category))
		mkdir($category,0777);
	if (!file_exists($cat_img_dir))
		mkdir($cat_img_dir,0777);
		
	$content = file_get_contents($url);
	// To fetch content from bitsontherun
	$result = new SimpleXmlElement($content);

	$xml = new DOMDocument("1.0");
	$rss = $xml->createElement('rss');
	$xml->appendChild($rss);
	
	$version = $xml->createAttribute('version');
	$rss->appendChild($version);
	
	$xmlns_dc = $xml->createAttribute('xmlns:dc');
	$rss->appendChild($xmlns_dc);
	
	$value = $xml->createTextNode('http://purl.org/dc/elements/1.1/');
	$xmlns_dc->appendChild($value);
	
	$value = $xml->createTextNode('2.0');
	$version->appendChild($value);
	
	$root = $xml->createElement("channel");
	$xml->appendChild($root);
	
	$title_m   		= $xml->createElement("title");
	$title_m_Text	= $xml->createTextNode($result->channel->title);
	$title_m->appendChild($title_m_Text);
	$root->appendChild($title_m);
	
	$link_m   		= $xml->createElement("link");
	$link_m_Text 	= $xml->createTextNode($result->channel->link);
	$link_m->appendChild($link_m_Text);
	$root->appendChild($link_m);	
	
	$description_m 	= $xml->createElement("description");
	$desc_m_Text 	= $xml->createTextNode($result->channel->description);
	$description_m->appendChild($desc_m_Text);
	$root->appendChild($description_m);	
	
	$language 		= $xml->createElement("language");
	$languageText 	= $xml->createTextNode($result->channel->language);
	$language->appendChild($languageText);
	$root->appendChild($language);
	
	$copyright 		= $xml->createElement("copyright");
	$copyrightText 	= $xml->createTextNode($result->channel->copyright);
	$copyright->appendChild($copyrightText);
	$root->appendChild($copyright);
	
	$pubDate   		= $xml->createElement("pubDate");
	$pubDateText 	= $xml->createTextNode($result->channel->pubDate);
	$pubDate->appendChild($pubDateText);
	$root->appendChild($pubDate);	
	
	$i=0;	
	$counter =  $count = 1;
	foreach($result->channel->item as $entry)
	{
		$title_v		= 	$entry->title;
		$link_v			=	$entry->link;
		$description_v	=	$entry->description;
		$pubDate_v		=	$entry->pubDate;
		$guid_v			=	$entry->guid;
		$feed_img_url	=	"";
		if ($media = $entry->children('media', TRUE)) {
				$attributes = $media->content->attributes();
				$img_url	=	$attributes->url;
				$ext = strrchr($img_url,".");
			 	//$img_name = $cat_img_dir . "image_" . $count . $ext;
				$img_name	=	$cat_img_dir."image_".($i+1).".jpg";
				
				
				 
				if($img_url != "")
				{	
					$a 			=	copy($img_url, $img_name);
					$img_info	=	getimagesize($img_name);     
					$iwidth		=	$img_info[0]; 
					$iheight 	=	$img_info[1]; 
					
					if($iwidth > $req_img_width ||  $iheight > $req_img_height)
						$img_resize = ysa_img_resize($img_name, $req_img_width, $req_img_height); 
										
					$new_img_path = $server_img_path.$cat_img_dir.$server_time."/images/image_".($i+1).".jpg";
					
					//echo $new_img_path."<br>";
					if ($img_name!="")
					{
						if ($s3->putObjectFile($img_name, $bucketName, $local_cat_img_dir.basename($img_name), S3::ACL_PUBLIC_READ, array(), array("Cache-Control" => "max-age=0 s-maxage=300")))
						{
							echo "File copied to {$bucketName}/".$cat_img_dir.basename($img_name)."<br>";
							$f_img_info	=	getimagesize($img_name);  
							$img_tag = '<p><img src="'.$new_img_path.'" width="'.$f_img_info[0].'" height="'.$f_img_info[1].'"></p>';
							$description_v = $description_v.$img_tag; 
							$feed_img_url = $new_img_path;
							$i++;					
						}
						else
							echo "There are some problem to upload file ".baseName($img_name)."<br>";	
					}
				}
		}
		$title   		= $xml->createElement("title");
		$titleText		= $xml->createTextNode($title_v);
		$title->appendChild($titleText);
		
		$link   		= $xml->createElement("link");
		$linkText 		= $xml->createTextNode($link_v);
		$link->appendChild($linkText);
		
		$description   	= $xml->createElement("description");
		$descText 		= $xml->createTextNode($description_v);
		$description->appendChild($descText);
		
		$pubDate   		= $xml->createElement("pubDate");
		$pubDateText 	= $xml->createTextNode($pubDate_v);
		$pubDate->appendChild($pubDateText);
	
		$guid  			= $xml->createElement("guid");
		$guidText 		= $xml->createTextNode($guid_v);
		$guid->appendChild($guidText);
			
		$item = $xml->createElement("item");
		$item->appendChild($title);
		$item->appendChild($link);
		
		if($feed_img_url != "")
		{
			$image			= $xml->createElement("image");
			$img_url_Text 	= $xml->createTextNode($feed_img_url);
			$image->appendChild($img_url_Text);	
			$item->appendChild($image);
		}
		
		$item->appendChild($description);
		$item->appendChild($pubDate);
		$item->appendChild($guid);
			
		$root->appendChild($item);
		
		if($counter==10) break;
		$counter++;
	}

	$rss->appendChild($root);
	$xml->formatOutput = false;
	$xml->saveXML();
	$xml->save($xml_file_name) or die("There are some Error to save XML file");	
	
	if(copy($xml_file_name,$rss_array[$r][1]."/".$xml_file_name))
	{
		if ($s3->putObjectFile($xml_file_name, $bucketName, $rss_array[$r][1]."/".$xml_file_name, S3::ACL_PUBLIC_READ,array(), 
                array("Cache-Control" => "max-age=0 s-maxage=300") ))		
			echo "<br>File copied to {$bucketName}/".$xml_file_name."<br><br>";
		else
			echo "<br>There are some problem to upload file ".baseName($xml_file_name)."<br>";	
			
		//unlink($xml_file_name);			
	}
	else
	{
		echo "<br>There are some problem to move file".$rss_array[$r][1]."/".$xml_file_name;
	}	
}


function ysa_clear_bucket($s3,$bucket,$contents){
	if(count($contents) > 0)
	{
		foreach ($contents as $p => $v):
			$s3->deleteObject($bucket, $p);
		endforeach;
	}
}

function ysa_img_resize($image_file, $targetW, $targetH) {
    
//Get Image Ext 
    $ExtAr = preg_split("@\.@", $image_file); 
    $image_ext = $ExtAr[count($ExtAr)-1];
    $image_ext = preg_replace ("@jpg@", "jpeg", $image_ext); 

    //Select The right Open function, 
    //And open the source, then get the current W / H
    $open_function = "imagecreatefrom" . $image_ext; 
    $SaveFunction = "image" . $image_ext; 
    $SourceIm = $open_function($image_file); 
    
    if (! $SourceIm ) return null; 
    
    $imAttr = getimagesize ($image_file); 
    
    $source_image_width = $imAttr[0]; 
    $source_image_height = $imAttr[1]; 
    
    //Create New Image
    $newImage = imagecreatetruecolor($targetW, $targetH); 
    //Fill Area with white
    $trans_colour = imagecolorallocatealpha($newImage, 255, 255, 255, 0);
    imagefill($newImage, 0, 0, $trans_colour);
    
    //Resize the image, and keep the aspect ratio
    $source_aspect_ratio = $source_image_width / $source_image_height;
    $thumbnail_aspect_ratio = $targetW / $targetH;
    
    $newWidth = $targetH * $source_aspect_ratio; 
    
     imagecopyresampled(
            $newImage, $SourceIm, 
            (($targetW - $newWidth)/2), 0, 
            0, 0, 
            $newWidth, $targetH, 
            $source_image_width, $source_image_height);
    
    $SaveFunction($newImage, $image_file, 90);
    imagedestroy($SourceIm);
    imagedestroy($newImage);
    
    return 1; 
    
    
}

?>
