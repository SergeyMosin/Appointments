<?php


namespace OCA\Appointments\Email;


use OCP\Mail\IEMailTemplate;

class EMailTemplate implements IEMailTemplate
{
    private $subject="";
    private $htmlBody="";
    private $textBody="";

    /** @inheritDoc */
    public function setSubject(string $subject){
        $this->subject=$subject;
    }

    /**
     * !!! NOT USED, DOES NOTHING !!!
     * @depreacted
     */
    public function addHeader(){}

    /**
     * !!! NOT USED, DOES NOTHING !!!
     * @depreacted
     * @param string $title
     * @param string $plainTitle
     */
    public function addHeading(string $title, $plainTitle = ''){}

    /**
     * @inheritDoc
     */
    public function addBodyText(string $text, $plainText = ''){
        // TODO: Implement addBodyText() method.
    }

    /**
     * @inheritDoc
     */
    public function addBodyListItem(string $text, string $metaInfo = '', string $icon = '', $plainText = '', $plainMetaInfo = '')
    {
        // TODO: Implement addBodyListItem() method.
    }

    /**
     * @inheritDoc
     */
    public function addBodyButtonGroup(string $textLeft, string $urlLeft, string $textRight, string $urlRight, string $plainTextLeft = '', string $plainTextRight = '')
    {
        // TODO: Implement addBodyButtonGroup() method.
    }

    /**
     * @inheritDoc
     */
    public function addBodyButton(string $text, string $url, $plainText = '')
    {
        // TODO: Implement addBodyButton() method.
    }

    /**
     * @inheritDoc
     */
    public function addFooter(string $text = '')
    {
        // TODO: Implement addFooter() method.
    }

    /** @inheritDoc */
    public function renderSubject(): string
    {
        return $this->subject;
    }

    /**
     * @inheritDoc
     */
    public function renderHtml(): string
    {
        // TODO: Implement renderHtml() method.
    }

    /** @inheritDoc */
    public function renderText(): string
    {
        // TODO: Implement renderText() method.
    }
}