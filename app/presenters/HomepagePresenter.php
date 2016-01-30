<?php

namespace App\Presenters;

use App\EmailQueue;
use Nette;
use Nette\Application\UI\Form;


class HomepagePresenter extends Nette\Application\UI\Presenter
{

    /**
     * @var EmailQueue
     * @inject
     */
    public $emailQueue;


    /**
     * @return Form
     */
    protected function createComponentEmailForm()
    {
        $form = new Form();
        $form->addText('from', 'Od');
        $form->addText('to', 'Komu');
        $form->addTextArea('body', 'Zpráva');

        $form->addSubmit("send", "Odeslat");
        $form->onSuccess[] = function (Form $form, $values) {
            $this->emailQueue->publish(
                $values->to,
                $values->from,
                $values->body
            );

            $this->flashMessage('Yay!');
            $this->redirect('this');
        };

        return $form;
    }

}
