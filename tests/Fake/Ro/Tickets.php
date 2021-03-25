
namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Tickets extends ResourceObject
{
    /**
     * @JsonSchema(schema="tickets.json")
     */
    public function onGet() : ResourceObject
    {
        return $this;
    }
}
