<?php

namespace Sunnysideup\Moodle\Model\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class GroupExtension extends DataExtension
{
    /**
     * @var string
     */
    private const MOODLE_PARENT_GROUP_CODE = 'MOODLES';

    /**
     * @var string
     */
    private const MOODLE_PARENT_GROUP_NAME = 'Moodle Groups';

    /**
     * @var string
     */
    private const MOODLE_PARENT_GROUP_EXPLANATION = 'This group holds all the Moodle Courses';

    /**
     * @var string
     */
    private const MOODLE_NAME_POST_FIX = '- COURSE';

    /**
     * @var string
     */
    private const MOODLE_GROUP_EXPLANATION = 'This group shows the members that are part of a Moodle Course';

    private static $db = [
        'MoodleUid' => 'Int',
        'CanEnrolWithMoodle' => 'Boolean',
        'DisplayName' => 'Varchar(100)',
        'Summary' => 'HTMLText',
        'StartDateTs' => 'Int',
        'EndDateTs' => 'Int',
    ];

    private static $indexes = [
        'MoodleUid' => true,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $fieldRepository =
        $fields->addFieldsToTab(
            'Root.Moodle',
            [
                CheckboxField::create(
                    'CanEnrolWithMoodle',
                    'User can enrol?'
                ),
                ReadonlyField::create(
                    'MoodleUid',
                    'Moodle Course ID'
                ),
                ReadonlyField::create(
                    'DisplayName',
                    'Display Name'
                ),
                HTMLEditorField::create(
                    'Summary',
                    'Summary'
                )->performReadonlyTransformation(),
                ReadonlyField::create(
                    'StartDate',
                    'Start Date',
                    $this->owner->StartDateNice()
                ),
                ReadonlyField::create(
                    'EndDate',
                    'End Date',
                    $this->owner->EndDateNice()
                ),
            ]
        );

        return $fields;
    }

    public function IsRegisteredOnCourse(?Member $member = null): bool
    {
        if (! $member) {
            $member = Security::getCurrentUser();
        }

        return $this->owner->Members()->filter(['ID' => $member->ID ?? 0])->count() > 0;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->owner->MoodleUid) {
            $holderGroup = $this->owner->findOrCreateMoodleHolderGroup();
            $this->owner->Locked = true;
            $this->owner->ParentID = $holderGroup->ID;
            if (! strpos($this->owner->Title, self::MOODLE_NAME_POST_FIX)) {
                $this->owner->Title .= ' ' . self::MOODLE_NAME_POST_FIX;
            }
            $this->owner->Description = self::MOODLE_GROUP_EXPLANATION;
        }
    }

    public static function create_group_from_moodle_data(array $moodleData): ?Group
    {
        $id = $moodleData['id'] ?? 0;
        if ($id) {
            $filter = ['MoodleUid' => $id];
            $group = DataObject::get_one(Group::class, $filter);
            if (! $group) {
                $group = Group::create($filter);
            }
            $group->Title = $moodleData['displayname'] ?? '';
            $group->Description = strip_tags($moodleData['summary'] ?? '');
            $group->DisplayName = $moodleData['displayname'] ?? '';
            $group->Summary = $moodleData['summary'] ?? '';
            $group->StartDateTs = $moodleData['startdate'] ?? 0;
            $group->EndDateTs = $moodleData['enddate'] ?? 0;
            $group->write();

            return $group;
        }

        return null;
    }

    /**
     * Influence the owner's canDelete() permission check value to be disallowed (false),
     * allowed (true) if no other processed results are to disallow, or open (null) to not
     * affect the outcome.
     *
     * See {@link DataObject::canDelete()} and {@link DataObject::extendedCan()} for context.
     *
     * @param Member $member
     *
     * @return null|bool
     */
    public function canDelete($member)
    {
        if ($this->owner->findOrCreateMoodleHolderGroup()->ID === $this->owner->ID || $this->owner->MoodleUid) {
            return false;
        }

        return null;
    }

    /**
     * Influence the owner's canDelete() permission check value to be disallowed (false),
     * allowed (true) if no other processed results are to disallow, or open (null) to not
     * affect the outcome.
     *
     * See {@link DataObject::canDelete()} and {@link DataObject::extendedCan()} for context.
     *
     * @param Member $member
     *
     * @return null|bool
     */
    public function canEdit($member)
    {
        if ($this->owner->findOrCreateMoodleHolderGroup()->ID === $this->owner->ID) {
            return false;
        }

        return null;
    }

    public function findOrCreateMoodleHolderGroup(): Group
    {
        $filter = ['Code' => self::MOODLE_PARENT_GROUP_CODE];
        $group = DataObject::get_one(Group::class, $filter);
        if (! $group) {
            $group = Group::create($filter);
        }
        $group->Sort = 99999;
        $group->Locked = true;
        $group->Title = self::MOODLE_PARENT_GROUP_NAME;
        $group->Description = self::MOODLE_PARENT_GROUP_EXPLANATION;
        $group->write();

        return DataObject::get_one(Group::class, $filter);
    }

    public function StartDateNice()
    {
        return $this->owner->StartDateTs ? date('Y-m-d', $this->owner->StartDateTs) : 'n/a';
    }

    public function EndDateNice()
    {
        return $this->owner->EndDateTs ? date('Y-m-d', $this->owner->EndDateTs) : 'n/a';
    }
}
