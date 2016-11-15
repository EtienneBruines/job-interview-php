<?php

namespace AppBundle\Controller;

use AppBundle\Entity\PayPal;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Skill;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $skills = $em->getRepository("AppBundle:Skill")->findAll();

        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
                "skills" => $skills,
            ]);
    }

    /**
     * @Route("/initialize", name="init")
     */
    public function initAction()
    {
        $skill = new Skill();
        $skill->setName('HTML Knowledge');
        $skill->setPrice(19.95);

        $em = $this->getDoctrine()->getManager();
        $em->persist($skill);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/purchase/{skillId}", name="purchase")
     * @Method("GET")
     */
    public function purchaseAction($skillId) {
        $em = $this->getDoctrine()->getManager();
        $skill = $em->getRepository("AppBundle:Skill")->find($skillId);

        $token = PayPal::getToken();

        $logger = $this->get('logger');
        $logger->info('token is: '.$token);

        return $this->render('default/purchase.html.twig', [
            'skill' => $skill,
            'token' => $token,
            'redirectUrl' => '',
        ]);
    }

    /**
     * @Route("/purchase/{skillId}", name="purchase_confirmed")
     * @Method("POST")
     */
    public function purchaseConfirmedAction($skillId) {
        $em = $this->getDoctrine()->getManager();
        $skill = $em->getRepository("AppBundle:Skill")->find($skillId);

        $payment = PayPal::createPayment("19.95", "test");

        return $this->redirect($payment->redirectUrl);
    }
}
