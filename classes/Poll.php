<?php

class Poll extends \ElggObject {

    const SUBTYPE = 'poll';

    /**
     * (non-PHPdoc)
     * @see ElggObject::initializeAttributes()
     */
    protected function initializeAttributes() {
        parent::initializeAttributes();

        $this->attributes['subtype'] = self::SUBTYPE;
    }

    /**
     * (non-PHPdoc)
     * @see ElggEntity::getURL()
     */
    public function getURL() {
        return elgg_normalize_url("poll/view/{$this->getGUID()}");
    }

    /**
     * (non-PHPdoc)
     * @see ElggObject::canComment()
     */
    public function canComment($user_guid = 0) {

        if ($this->comments_allowed !== 'yes') {
            return false;
        }

        return parent::canComment($user_guid);
    }

    /**
     * Get the stored answers
     *
     * @return void|array
     */
    public function getAnswers() {
        return @json_decode($this->answers, true);
    }

    /**
     * Get the answers in a way to use in input/radios
     *
     * @return array
     */
    public function getAnswersOptions() {

        $answers = $this->getAnswers();
        if (empty($answers)) {
            return [];
        }

        $result = [];
        foreach ($answers as $answer) {
            $name = elgg_extract('name', $answer);
            $label = elgg_extract('label', $answer);

            $result[$label] = $name;
        }

        return $result;
    }

    /**
     * Get the label assosiated with a answer-name
     *
     * @param string $answer the answer name
     *
     * @return false|string
     */
    public function getAnswerLabel($answer) {

        $answers = $this->getAnswers();
        if (empty($answers)) {
            return false;
        }

        foreach ($answers as $stored_answer) {
            $name = elgg_extract('name', $stored_answer);
            if ($name !== $answer) {
                continue;
            }

            return elgg_extract('label', $stored_answer);
        }

        return false;
    }

    /**
     * Check if a user can vote for this poll
     *
     * @param int $user_guid (optional) user_guid to check, defaults to current user
     *
     * @return bool
     */
    public function canVote($user_guid = 0) {

        $user_guid = sanitise_int($user_guid, false);
        if (empty($user_guid)) {
            $user_guid = elgg_get_logged_in_user_guid();
        }

        if (empty($user_guid)) {
            return false;
        }

        if (!$this->getAnswers()) {
            return false;
        }


        if ($this->getVote() && (poll_get_plugin_setting('vote_change_allowed') !== 'yes')) {
            return false;
        }
        // check close date
        if ($this->close_date) {
            $close_date = (int) $this->close_date;
            if ($close_date < time()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register a vote for a user
     *
     * @param string $answer    the answer
     * @param int    $user_guid (optional) the user who is voting, defaults to current user
     *
     * @return bool
     */
    public function vote($answer, $user_guid = 0) {

        $user_guid = sanitise_int($user_guid, false);
        if (empty($user_guid)) {
            $user_guid = elgg_get_logged_in_user_guid();
        }

        if (empty($user_guid)) {
            return false;
        }

        $answer_options = $this->getAnswersOptions();
        if (!in_array($answer, $answer_options)) {
            return false;
        }

        $existing_vote = $this->getVote(false, $user_guid);
        if (!empty($existing_vote)) {
            $existing_vote->value = $answer;
            return (bool) $existing_vote->save();
        }

        $annotation_id = $this->annotate('vote', $answer, $this->access_id, $user_guid);
        if (empty($annotation_id)) {
            return false;
        }

        if (elgg_get_plugin_setting('add_vote_to_river', 'poll', 'yes') !== 'no') {
            elgg_create_river_item([
                'view' => 'river/object/poll/vote',
                'action_type' => 'vote',
                'subject_guid' => $user_guid,
                'object_guid' => $this->getGUID(),
                'target_guid' => $this->getContainerGUID(),
                'annotation_id' => $annotation_id,
                'access_id' => $this->access_id,
            ]);
        }
        return true;
    }

    public function multiVote($answers, $user_guid = 0) {

        $user_guid = sanitise_int($user_guid, false);
        if (empty($user_guid)) {
            $user_guid = elgg_get_logged_in_user_guid();
        }

        if (empty($user_guid)) {
            return false;
        }

        $answer_options = $this->getAnswersOptions();
        /* if (!in_array($answer, $answer_options)) {
          return false;
          }// */
        $existing_votes = $this->getVote(false, $user_guid);
        $annotation_ids = array();
        // if existing vote
        if ($existing_votes) {
            // count answer and existing vote
            $nbExistingVote = count($existing_votes);
            $nbAnswer = count($answers);

            // if there are more awnswer than existing vote
            if ($nbExistingVote < $nbAnswer) {
                // update existing
                foreach ($existing_votes as $existing_vote) {
                    $annotate = elgg_get_annotation_from_id($existing_vote->id);
                    $annotate->value = array_pop($answers);
                    $annotate->save();
                }

                // if there is one aswer left, create a new annotation
                if (count($answers) > 0) {
                    foreach ($answers as $answer) {
                        $annotation_ids[] = $this->annotate('vote', $answer, $this->access_id, $user_guid);
                        if (empty($annotation_ids)) {
                            return false;
                        }
                    }
                }
            } else {

                foreach ($existing_votes as $existing_vote) {
                    $annotate = elgg_get_annotation_from_id($existing_vote->id);
                    $annotate->value = array_pop($answers);
                    $annotate->save();
                }
            }
        } else {
            foreach ($answers as $answer) {
                $annotation_ids[] = $this->annotate('vote', $answer, $this->access_id, $user_guid);
                if (empty($annotation_ids)) {
                    return false;
                }
            }
        }
        if (elgg_get_plugin_setting('add_vote_to_river', 'poll', 'yes') !== 'no') {
            foreach ($annotation_ids as $annotation_id) {
                elgg_create_river_item([
                    'view' => 'river/object/poll/vote',
                    'action_type' => 'vote',
                    'subject_guid' => $user_guid,
                    'object_guid' => $this->getGUID(),
                    'target_guid' => $this->getContainerGUID(),
                    'annotation_id' => $annotation_id,
                    'access_id' => $this->access_id,
                ]);
            }
        }
        return true;
    }

    /**
     * Get the vote of a user
     * @param int $user_guid the user to get the vote for, defaults to current user
     *
     * @return false|string|\ElggAnnotation
     */
    public function getVote($value_only = false, $user_guid = 0) {
        $value_only = (bool) $value_only;
        $user_guid = sanitise_int($user_guid, false);
        if (empty($user_guid)) {
            $user_guid = elgg_get_logged_in_user_guid();
        }

        if (empty($user_guid)) {
            return false;
        }

        $annotations = $this->getAnnotations([
            'annotation_name' => 'vote',
            'annotation_owner_guid' => $user_guid,
            'limit' => 0,
                //'limit' => 1,
        ]);

        /* if (empty($annotations)) {
          return false;
          }// */
        if ($value_only) {
            return $annotations[0]->value;
        }

        return $annotations;
        //return $annotations[0];
    }

    /**
     * Get all the votes in a count array
     *
     * @return false|array
     */
    public function getVotes() {

        $answers = $this->getAnswers();
        if (empty($answers)) {
            return [];
        }

        $results = [];
        foreach ($answers as $answer) {
            $name = elgg_extract('name', $answer);
            $label = elgg_extract('label', $answer);
            $short_label = elgg_get_excerpt($label, 20);

            $results[$name] = [
                'label' => $short_label,
                'full_label' => $label,
                'value' => 0,
                'color' => '#' . substr(md5($name), 0, 6),
            ];
        }

        $votes = $this->getAnnotations([
            'annotation_name' => 'vote',
            'limit' => false,
        ]);

        if (empty($votes)) {
            return false;
        }

        foreach ($votes as $vote) {
            $name = $vote->value;
            if (!isset($results[$name])) {
                // no longer an option
                continue;
            }

            $results[$name]['value'] ++;
        }

        return array_values($results);
    }

    /**
     * Notify the owner of the poll that it's closed
     *
     * @return array
     */
    public function notifyOwnerOnClose() {

        $owner = $this->getOwnerEntity();

        // make notification subject / body
        $subject = elgg_echo('poll:notification:close:owner:subject', [$this->title]);
        $summary = elgg_echo('poll:notification:close:owner:summary', [$this->title]);
        $message = elgg_echo('poll:notification:close:owner:body', [
            $owner->name,
            $this->title,
            $this->getURL(),
        ]);

        // prepare some params
        $params = [
            'object' => $this,
            'action' => 'close',
            'summary' => $summary,
        ];
        return notify_user($owner->getGUID(), $owner->getGUID(), $subject, $message, $params);
    }

    /**
     * Notify the participants of the poll that it's closed
     *
     * @return array
     */
    public function notifyParticipantsOnClose() {

        // this could take a while
        set_time_limit(0);

        $owner = $this->getOwnerEntity();

        $annotation_options = [
            'guid' => $this->getGUID(),
            'limit' => false,
            'annotation_name' => 'vote',
            'callback' => function($row) {
                return (int) $row->owner_guid;
            },
        ];

        $ia = elgg_set_ignore_access(true);
        $participants = elgg_get_annotations($annotation_options);
        elgg_set_ignore_access($ia);

        if (empty($participants)) {
            // nobody voted :(
            return [];
        }

        $participants = array_unique($participants);

        // make notification subject / body
        $subject = elgg_echo('poll:notification:close:participant:subject', [$this->title]);
        $summary = elgg_echo('poll:notification:close:participant:summary', [$this->title]);
        $message = elgg_echo('poll:notification:close:participant:body', [
            $this->title,
            $this->getURL(),
        ]);

        // prepare some params
        $params = [
            'object' => $this,
            'action' => 'close',
            'summary' => $summary,
        ];
        return notify_user($participants, $owner->getGUID(), $subject, $message, $params);
    }

}
