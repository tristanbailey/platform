<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Read Config Parser
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Tool\Parser;
use Ushahidi\Core\Exception\ParserException;
use Ushahidi\Core\Usecase\Config\ReadConfigData;

class Ushahidi_Parser_Config_Read implements Parser
{
	public function __invoke(Array $data)
	{
		$valid = Validation::factory($data)
			->rules('id', [
					['not_empty'],
				]);

		if (!$valid->check())
		{
			throw new ParserException("Failed to parse config read request", $valid->errors('config'));
		}

		return new ReadConfigData($data);
	}
}

