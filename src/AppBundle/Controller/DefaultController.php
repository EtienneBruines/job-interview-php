<?php

namespace AppBundle\Controller;

use AppBundle\Entity\PayPal;
use AppBundle\Entity\Skill;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

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

        if (!$skill)
            return $this->redirectToRoute('homepage');

        return $this->render('default/purchase.html.twig', [
            'skill' => $skill,
        ]);
    }

    /**
     * @Route("/purchase/{skillId}", name="purchase_confirmed")
     * @Method("POST")
     */
    public function purchaseConfirmedAction($skillId) {
        $em = $this->getDoctrine()->getManager();
        $skill = $em->getRepository("AppBundle:Skill")->find($skillId);

        if (!$skill)
            return $this->redirectToRoute('homepage');

        $payment = PayPal::createPayment($skill->id, $skill->price, "Purchase of ".$skill->name);
        $em->persist($payment);
        $em->flush();

        return $this->redirect($payment->redirectUrl);
    }

    /**
     * @Route("/purchase-success", name="purchase-success")
     */
    public function purchaseSuccessAction(Request $request) {
        $paymentId = $request->get('paymentId');
        $payerId = $request->get('PayerID');

        if (!$paymentId)
            throw new \Exception("paymentId was not set");

        if (!$payerId)
            throw new \Exception("payerId was not set");

        $em = $this->getDoctrine()->getManager();
        $payment = $em->getRepository("AppBundle:Payment")->findOneBy(array(
            'paypalId' => $paymentId,
        ));

        if (!$payment)
            throw new \Exception("Payment not found");

        if ($payment->state == "created")
        {
            PayPal::executePayment($payment, $payerId);
            $em->persist($payment);
            $em->flush();
        }
        $skill = $em->getRepository("AppBundle:Skill")->find($payment->skillId);

        if (!$skill)
            throw new \Exception("Skill not found");

        return $this->render('default/purchase-success.html.twig', [
            'skill' => $skill,
            'state' => $payment->state,
        ]);
    }

    /**
     * @Route("/purchase-cancel", name="purchase-cancel")
     */
    public function purchaseCancelAction(Request $request)
    {
        $token = $request->get('token');

        if (!$token)
            throw new \Exception("token was not set");

        return $this->render('default/purchase-cancel.html.twig', [
            'token' => $token,
        ]);
    }
}
