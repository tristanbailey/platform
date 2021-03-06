<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Tag Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Data;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Entity\Message;
use Ushahidi\Core\Usecase\Message\CreateMessageRepository;
use Ushahidi\Core\Usecase\Message\UpdateMessageRepository;
use Ushahidi\Core\Usecase\Message\DeleteMessageRepository;
use Ushahidi\Core\Usecase\Message\MessageData;

class Ushahidi_Repository_Message extends Ushahidi_Repository implements
	UpdateMessageRepository,
	CreateMessageRepository
{

	// Ushahidi_Repository
	protected function getTable()
	{
		return 'messages';
	}

	// CreateRepository
	// ReadRepository
	public function getEntity(Array $data = null)
	{
		return new Message($data);
	}

	// Ushahidi_Repository
	protected function setSearchConditions(SearchData $search)
	{
		$query = $this->search_query
			->join('contacts')
				->on('contact_id', '=', 'contacts.id');

		if ($search->box === 'outbox')
		{
			// Outbox only shows outgoing messages
			$query->where('direction', '=', 'outgoing');
		}
		elseif ($search->box === 'inbox')
		{
			// Inbox only shows incoming messages
			$query->where('direction', '=', 'incoming');
		}

		// Get the requested status, which is secondary to box
		$status = $search->status;

		if ($search->box === 'archived')
		{
			// Archive only shows archived messages
			$query->where('status', '=', 'archived');
		}
		else if ($status)
		{
			if ($status !== 'all') {
				// Search for a specific status
				$query->where('status', '=', $status);
			}
		}
		else
		{
			// Other boxes do not display archived
			$query->where('status', '!=', 'archived');
		}

		if (! empty($search->q))
		{
			$q = $search->q;
			$query->and_where_open();
			$query->where('contacts.contact', 'LIKE', "%$q%");
			$query->or_where('title', 'LIKE', "%$q%");
			$query->or_where('message', 'LIKE', "%$q%");
			$query->and_where_close();
		}

		foreach ([
			'contact',
			'parent',
			'post',
		] as $fk)
		{
			if ($search->$fk)
			{
				$query->where("messages.{$fk}_id", '=', $search->$fk);
			}
		}

		foreach ([
			'type',
			'data_provider',
		] as $key)
		{
			if ($search->$key)
			{
				$query->where("messages.{$key}", '=', $search->$key);
			}
		}
	}

	// CreateRepository
	public function create(Data $data)
	{
		$record = array_filter($data->asArray());

		// New messages cannot have any other state
		$record['status']    = \Message_Status::PENDING;
		$record['direction'] = \Message_Direction::OUTGOING;
		$record['created']   = time();

		return $this->executeInsert($record);
	}

	// UpdateRepository
	public function update($id, Data $input)
	{
		$message = $this->get($id);
		if ($message->direction === \Message_Direction::INCOMING)
		{
			// For incoming messages, users can't actually edit a message, only
			// archived/unarchive and associate with post. Strip everything else.
			$update_data = Arr::extract($input->asArray(), ['status', 'post_id']);
		} else {
			// For outgoing messages. Update most values, exclude direction and parent id.
			$update_data = Arr::extract($input->asArray(), [
				'contact_id',
				'data_provider',
				'title',
				'message',
				'datetime',
				'type',
				'status',
			]);
		}

		$this->executeUpdate(compact('id'), $update_data);
	}

	// UpdateMessageRepository
	public function checkStatus($status, $direction)
	{
		if ($direction === \Message_Direction::INCOMING)
		{
			// Incoming messages can only be: received, archived
			return in_array($status, [
				\Message_Status::RECEIVED,
				\Message_Status::ARCHIVED,
			]);
		}

		if ($direction === \Message_Direction::OUTGOING)
		{
			// Outgoing messages can only be: pending, cancelled, failed, unknown, sent
			return in_array($status, [
				\Message_Status::PENDING,
				\Message_Status::EXPIRED,
				\Message_Status::CANCELLED,
			]);
		}

		return false;
	}

	/**
	 * For CreateMessageRepository
	 * @param  int $parent_id
	 * @return Boolean
	 */
	public function parentExists($parent_id)
	{
		return $this->selectCount(['id' => $parent_id]) > 0;
	}
}
