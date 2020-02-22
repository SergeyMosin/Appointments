<?php


namespace OCA\Appointments;


use OCP\AppFramework\Http\Response;

class SendDataResponse extends Response{

    private $data="";

    public function setData(string $data){
        $this->data=$data;
    }

    public function render()
    {
        return $this->data;
    }

}