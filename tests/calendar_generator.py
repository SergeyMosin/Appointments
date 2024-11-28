from icalendar import Calendar, Event
from datetime import datetime, timedelta
import random
import pytz

cal = Calendar()
cal.add('prodid', '-//Test Calendar Generator//EN')
cal.add('version', '2.0')
cal.add('calscale', 'GREGORIAN')

def random_string(length):
    chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"
    return ''.join(random.choice(chars) for _ in range(length))

utc = pytz.UTC

start_date = datetime.now(utc) - timedelta(days=1)

for _ in range(1500):
    event = Event()

    event_start = start_date + timedelta(days=random.randint(0, 21), hours=random.randint(0, 23), minutes=random.randint(0, 59))
    event_end = event_start + timedelta(hours=random.randint(1, 3))
    
    event.add('uid', f"{random_string(32)}@example.com")
    event.add('dtstamp', datetime.now(utc))
    event.add('summary', random_string(10))
    event.add('description', random_string(50))
    event.add('dtstart', event_start)
    event.add('dtend', event_end)
    
    cal.add_component(event)

file_path = 'test_calendar.ics'
with open(file_path, 'wb') as f:
    f.write(cal.to_ical())

print(f"Calendar saved to {file_path}")
