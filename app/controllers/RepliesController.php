<?php

namespace Phosphorum\Controllers;

use Phosphorum\Models\Users,
	Phosphorum\Models\Posts,
	Phosphorum\Models\PostsReplies,
	Phalcon\Http\Response;

class RepliesController extends \Phalcon\Mvc\Controller
{

	public function initialize()
	{
		$this->view->disable();
	}

	/**
	 * Returs the raw comment as it as edited
	 *
	 * @param int $id
	 */
	public function getAction($id)
	{

		$response = new Response();

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			$response->setStatusCode('401', 'Unauthorized');
			return $response;
		}

		$postReply = PostsReplies::findFirst(array(
			'id = ?0 AND (users_id = ?1 OR "Y" = ?2)',
			'bind' => array($id, $usersId, $this->session->get('identity-moderator'))
		));
		if ($postReply) {
			$data = array('status' => 'OK', 'id' => $postReply->id, 'comment' => $postReply->content);
		} else {
			$data = array('status' => 'ERROR');
		}

		$response->setContent(json_encode($data));
		return $response;
	}

	/**
	 * Updates a reply
	 */
	public function updateAction()
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			return $this->response->redirect();
		}

		if (!$this->request->isPost()) {
			return $this->response->redirect();
		}

		$postReply = PostsReplies::findFirst(array(
			'id = ?0 AND (users_id = ?1 OR "Y" = ?2)',
			'bind' => array($this->request->getPost('id'), $usersId, $this->session->get('identity-moderator'))
		));
		if (!$postReply) {
			return $this->response->redirect();
		}

		$content = $this->request->getPost('content');
		if (trim($content)) {
			$postReply->content = $content;
			$postReply->edited_at = time();
			$postReply->save();
		}

		return $this->response->redirect('discussion/' . $postReply->post->id . '/' . $postReply->post->slug . '#C' . $postReply->id);
	}

	/**
	 * Deletes a reply
	 *
	 * @param int $id
	 */
	public function deleteAction($id)
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			return $this->response->setStatusCode('401', 'Unauthorized');
		}

		$postReply = PostsReplies::findFirst(array(
			'id = ?0 AND (users_id = ?1 OR "Y" = ?2)',
			'bind' => array($id, $usersId, $this->session->get('identity-moderator'))
		));
		if ($postReply) {

			if ($postReply->delete()) {
				if ($usersId != $postReply->post->users_id) {

					$user = Users::findFirstById($usersId);
					$user->karma -= 10;
					$user->vote_points -= 10;
					$user->save();

					$postReply->post->number_replies--;
					$postReply->post->save();
				}
			}

			return $this->response->redirect('discussion/' . $postReply->post->id . '/' . $postReply->post->slug);
		}

		return $this->response->redirect();
	}

	/**
	 * Votes a post up
	 */
	public function voteUpAction($id = 0)
	{
		$response = new Response();

		/**
		 * Find the post using get
		 */
		$postReply = PostsReplies::findFirstById($id);
		if (!$postReply) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'Post reply does not exist'
			));
		}

		$user = Users::findFirstById($this->session->get('identity'));
		if (!$user) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You must log in first to vote'
			));
		}

		if ($user->votes <= 0) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You don\'t have enough votes available'
			));
		}

		$postReply->votes_up++;
		if ($postReply->users_id != $user->id) {
			$postReply->user->karma += 5;
			$postReply->user->vote_points += 5;
		}

		if ($postReply->save()) {
			$user->votes--;
			if (!$user->save()) {
				foreach ($user->getMessages() as $message) {
					return $response->setJsonContent(array(
						'status' => 'error',
						'message' => $message->getMessage()
					));
				}
			}
		}

		return $response->setJsonContent(array(
			'status' => 'OK'
		));
	}

	/**
	 * Votes a post down
	 */
	public function voteDownAction($id = 0)
	{
		$response = new Response();

		/**
		 * Find the post using get
		 */
		$postReply = PostsReplies::findFirstById($id);
		if (!$postReply) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'Post reply does not exist'
			));
		}

		$user = Users::findFirstById($this->session->get('identity'));
		if (!$user) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You must log in first to vote'
			));
		}

		if ($user->votes <= 0) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You don\'t have enough votes available'
			));
		}

		$postReply->votes_down++;
		if ($postReply->users_id != $user->id) {
			$postReply->user->karma -= 5;
			$postReply->user->vote_points -= 5;
		}

		if ($postReply->save()) {
			$user->votes--;
			if (!$user->save()) {
				foreach ($user->getMessages() as $message) {
					return $response->setJsonContent(array(
						'status' => 'error',
						'message' => $message->getMessage()
					));
				}
			}
		}

		return $response->setJsonContent(array(
			'status' => 'OK'
		));
	}

}