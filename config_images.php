<?php

require_once('app/Mage.php'); //Path to Magento
umask(0);
Mage::app();

$COUNTSTART = 0;

ini_set('memory_limit','4096M');

echo "Getting configurable products... ";
$configProducts = Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('type_id', 'configurable');
$total = $configProducts->getSize();
echo "done.\nFound $total\n";

$count = 0;


$apiDirect = new Mage_Catalog_Model_Product_Attribute_Media_Api;


foreach($configProducts as $configProduct) {
    $count++;
    
    if($count < $COUNTSTART)
        continue;
    
    echo "Parsing product {$configProduct->getSku()} ({$configProduct->getId()}) ($count of $total)\n";
    $productModel = Mage::getModel('catalog/product')->load($configProduct->getId());
            
    $gallery = $productModel->getData('media_gallery');
    $currentImagesCount = count($gallery['images']);

    $colors = array();
    
    $childProducts = Mage::getModel('catalog/product_type_configurable')
                    ->getUsedProducts(null, $configProduct);

    echo "\tFound ".count($childProducts)." simple products\n";
    
    foreach($childProducts as $childProduct) {
        $color = $childProduct->getAttributeText('color');
        if(!in_array($color, $colors)) {
            $image = Mage::helper('catalog/image')->init($childProduct, 'image');
            
            $imageUrl = $image->__toString();
            
            //Don't parse placeholder images
            if(strpos($imageUrl, 'placeholder'))
                continue;
            
            $imageFile = str_replace(
                array(Mage::getBaseUrl('media'), '/'),
                array(Mage::getBaseDir('media').DS, DS),
                $imageUrl
            );
            
            $name = $productModel->getName();
            $label = $name.' -- '.$color;
            
            echo "\tProduct has $currentImagesCount images. ".($currentImagesCount === 0 ? 'Main Image' : 'Extra Image')."\n";
            
            $mageImage = getImageObject($label, $imageFile, $currentImagesCount+1);
            
            $r = $apiDirect->create($configProduct->getSku(), $mageImage) ? true : "An error occured assigning ".h($imageName)." to product ".h($product['sku']);
            
            if($r === true) {
                $currentImagesCount++;
                $colors[] = $color;
                echo "\tImage added.\n";
            }
            
            else {
                echo "\tERROR: didn't add the image.";
            }
        }
    }//foreach childProducts
    
//    if($count == 38)
//        die();
}


function getImageObject($label, $imageFileName, $position) {
    $name = str_replace(LABEL_COLOR_SEPARATOR, '', $label);
        
    $imgObject = array(
        'file' => array(
            //Clean Up the name and make it URL Friendly
            'name' => createSeoName($name, 50),
            //Get the content of the file
            'content' => base64_encode(file_get_contents($imageFileName)),
            'mime' => 'image/jpeg'
        ),
        'label' => ucwords(strtolower($label)),
        'position' => $position,
        'exclude' => 0
    );

    //If first image, it is main image, so make it all the image types availabe in magento
    if ($position == 1) {
        $imgObject['types'] = array('small_image', 'image', 'thumbnail');
    }

    return $imgObject;
}

function createSeoName($mageName, $maxLength) {
    $result = strtolower($mageName);

    $result = preg_replace("/[^a-z0-9\s-]/", "", $result);
    $result = trim(preg_replace("/[\s-]+/", " ", $result));
    $result = trim(substr($result, 0, $maxLength));
    $result = preg_replace("/\s/", "-", $result);

    return $result;
}