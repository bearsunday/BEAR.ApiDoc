
namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Ticket extends ResourceObject
{
    /**
     * @JsonSchema(key="ticket", schema="ticket.schema.json", params="ticket.param.json")
     */
    public function onGet(string $id) : ResourceObject
    {
        unset($id);
    }

    public function onPost(
        string $title,
        string $description = 'default desc',
        string $assignee = 'default assignee'
    ) : ResourceObject {
        return $this;
    }
}
