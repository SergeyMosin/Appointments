<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class ExternalModeSabrePlugin extends ServerPlugin {
    // https://sabre.io/dav/writing-plugins/

    const AUTO_FIX_URI="auto_fix_uri";

    private $afx_uri;

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    function initialize(Server $server){
        $IUser=\OC::$server->getUserSession()->getUser();
        if($IUser!==null){
            $uri=\OC::$server->getConfig()->getUserValue($IUser->getUID(),Application::APP_ID,self::AUTO_FIX_URI);
            if(!empty($uri)){
                // User is in External mode and "Auto-fix" is enabled
                $this->afx_uri=$uri;
//                $server->on('beforeWriteContent', [$this,'autoFix']);
                $server->on('beforeCreateFile', [$this,'autoFix']);
            }
        }
    }

//    function autoFix($path, \Sabre\DAV\IFile $node, &$data, &$modified){
    function autoFix($path, &$data, \Sabre\DAV\ICollection $parent, &$modified){

        $afu=$this->afx_uri;
        if(substr($path,-4)===".ics" && ($pos=strpos($path,$afu))!==false){
            $pos+=strlen($afu);
            if(strpos($path,"/",$pos)===false){
                // Incoming event belongs to this user...
                if (is_resource($data)) {
                    $data = stream_get_contents($data);
                }
                $es=strpos($data,"\r\nBEGIN:VEVENT\r\n");
                if($es!==false){
                    $es+=12;
                    $ee=strpos($data,"\r\nEND:VEVENT\r\n",$es);
                    if($ee===false){
                        // bad data
                        return;
                    }
                    $t=strpos($data,"\r\nTRANSP:TRANSPARENT\r\n",$es);
                    if($t!=false && $t<$ee){
                        // already free
                        return;
                    }
                    $d_pos=strpos($data,"\r\nDESCRIPTION:_",$es);
                    if($d_pos!==false && $d_pos<$ee){
                        // If we are here, the user wants to fix transparency...
                        // ... it can be "not set" or be "OPAQUE" at this point
                        $_data=$data;

                        $t=strpos($data,"\r\nTRANSP:OPAQUE\r\n",$es);
                        if($t!==false && $t<$ee){
                            // remove it
                            $_data=substr($_data,0,$t).substr($_data,$t+15);
                            $ee-=15;
                            // Recalculate because it can be after "TRANSP:"
                            $d_pos=strpos($_data,"\r\nDESCRIPTION:_",$es);
                        }

                        $add="\r\nTRANSP:TRANSPARENT";

                        $t=strpos($data,"\r\nCATEGORIES:",$es);
                        if($t===false || $t>$ee){
                            $add.="\r\nCATEGORIES:".BackendUtils::APPT_CAT;
                        }

                        // This also removes "DESCRIPTION" if the "_" is the only char.
                        $data=substr($_data,0,$d_pos).$add.substr($_data,$d_pos+($_data[$d_pos+15]==="\r"?15:0));

                        $modified=true;
                    }
                }
            }
        }
    }
}