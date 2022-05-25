<?php

/**
 * @copyright Copyright (c) 2022 Igor Oliveira <igor.oliveira@metaprovide.org>
 *
 * @author Igor Oliveira <igor.oliveira@metaprovide.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Appointments\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class Provider implements IProvider
{
	public const SUBJECT_ADD = 'booking_add';
	public const SUBJECT_CONFIRM = 'booking_confirm';
	public const SUBJECT_CANCEL = 'booking_cancel';
	public const SUBJECT_SKIP = 'booking_skip';
	public const SUBJECT_TYPE_CHANGE = 'booking_type_change';
	public const SUBJECT_OTHER = 'booking_other';

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IManager */
	protected $activityManager;

	/** @var IUrlGenerator */

	protected $url;

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager)
	{
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null)
	{
		if ($event->getApp() !== 'appointments' || $event->getType() !== 'appointment') {
			throw new \InvalidArgumentException();
		}

		$subjectParameters = $event->getSubjectParameters();

		$this->l = $this->languageFactory->get('apppointments', $language);

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'places/calendar-dark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('/custom_apps/appointments/', 'app-dark.svg')));
		}

		if ($event->getSubject() === self::SUBJECT_ADD) {
			$subject = $this->l->t('{booking} has created a new booking for {dtStart}');
		} elseif ($event->getSubject() === self::SUBJECT_CONFIRM) {
			$subject = $this->l->t('{booking} has confirmed a booking for {dtStart}');
		} elseif ($event->getSubject() === self::SUBJECT_CANCEL) {
			$subject = $this->l->t('{booking} has cancelled a booking for {dtStart}');
		} elseif ($event->getSubject() === self::SUBJECT_OTHER) {
			$subject = $this->l->t('Booking for {booking} at {dtStart} has been modified');
		} else {
			throw new \InvalidArgumentException();
		}

		$this->setSubjects($event, $subject, $subjectParameters);

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters)
	{
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}
}
