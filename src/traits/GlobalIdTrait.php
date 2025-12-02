<?php

namespace yii\graphql\traits;

/**
 * TODO: Expand Relay support (Node interface, node(id) query, optional module config)
 * so these helpers integrate with the built-in schema instead of being standalone utilities.
 */
trait GlobalIdTrait
{
    /**
     * Create global id.
     *
     * @param  string $type
     * @param  string|integer $id
     * @return string
     */
    public function encodeGlobalId($type, $id)
    {
        return base64_encode($type . ':' . $id);
    }

    /**
     * Decode the global id.
     *
     * @param  string $id
     * @return array
     */
    public function decodeGlobalId($id)
    {
        return explode(":", base64_decode($id));
    }

    /**
     * Get the decoded id.
     *
     * @param  string $id
     * @return string
     */
    public function decodeRelayId($id)
    {
        list($type, $id) = $this->decodeGlobalId($id);

        return $id;
    }

    /**
     * Get the decoded GraphQL Type.
     *
     * @param  string $id
     * @return string
     */
    public function decodeRelayType($id)
    {
        list($type, $id) = $this->decodeGlobalId($id);

        return $type;
    }
}
