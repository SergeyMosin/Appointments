<?php


namespace OCA\Appointments\Controller;


use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\Share\IManager;

class PublicController extends Controller{

    private $userId;
    private $logger;
    private $manager;

    public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                ILogger $logger,
                                IManager $manager
    ){
        parent::__construct($AppName, $request);
        $this->userId=$UserId;
        $this->logger=$logger;
        $this->manager=$manager;
    }


    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function form2(){
        $this->logger->error("fffffffffffffffffffff");

        $tr=new TemplateResponse($this->appName,
            'public/form2',
            [],
            'base');

        return $tr;
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function form2post(){


    }

}