<?php

/*
 * This file is part of the Doctrine DBAL Util package.
 *
 * (c) Jean-Bernard Addor
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Security;

use AppBundle\Entity\URL;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UrlVoter__unused extends Voter
{
    // these strings are just invented: you can use anything
    const VIEW = 'view';
    // const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [
                self::VIEW,
                // self::EDIT,
            ], true)) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof URL) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // you know $subject is a Post object, thanks to supports
        /** @var URL $post */
        $url = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($url, $user);
            // case self::EDIT:
                // return $this->canEdit($url, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(URL $url, User $user)
    {
        // if they can edit, they can view
        // if ($this->canEdit($post, $user)) {
        //     return true;
        // }

        // the Post object could have, for example, a method isPrivate()
        // that checks a boolean $private property
        return $url->getUsers()->contains($user);
    }

    /*
        private function canEdit(Post $post, User $user)
        {
            // this assumes that the data object has a getOwner() method
            // to get the entity of the user who owns this data object
            return $user === $post->getOwner();
        }
    */
}
