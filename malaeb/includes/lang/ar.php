<?php
// ============================================================
//  lang/ar.php — Arabic translations.
//  Key = the English text exactly as it appears in the code or in a
//  template's {{t:...}} marker. Keep :placeholders (:count, :days ...) as they
//  are; they can move anywhere in the Arabic sentence.
//  tests/i18n_tests.php fails if a key used anywhere is missing here.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

return [
    // --- brand, layout, navigation ---------------------------------------
    'Wagti'                     => 'وقتي',
    'WAGTI.'                    => 'وقتي.',
    'Your time. Your court.'    => 'وقتك. ملعبك.',
    'Wagti. All rights reserved.' => 'وقتي. جميع الحقوق محفوظة.',
    'Wagti - book football, padel, basketball, volleyball and tennis courts across Riyadh in seconds.'
        => 'وقتي - احجز ملاعب كرة القدم والبادل وكرة السلة والطائرة والتنس في الرياض خلال ثوانٍ.',
    'Skip to content'           => 'انتقل إلى المحتوى',
    'Main'                      => 'الرئيسية',
    'Home'                      => 'الرئيسية',
    'Courts'                    => 'الملاعب',
    'My Bookings'               => 'حجوزاتي',
    'My bookings'               => 'حجوزاتي',
    'My Courts'                 => 'ملاعبي',
    'My courts'                 => 'ملاعبي',
    'Admin'                     => 'الإدارة',
    'Logout'                    => 'خروج',
    'Login'                     => 'دخول',
    'Log in'                    => 'تسجيل الدخول',
    'Register'                  => 'حساب جديد',

    // --- page titles -----------------------------------------------------
    'Book a court'              => 'احجز ملعب',
    'Checkout'                  => 'الدفع',
    'Admin Dashboard'           => 'لوحة الإدارة',
    'Admin dashboard'           => 'لوحة الإدارة',
    'Edit booking'              => 'تعديل الحجز',
    'All bookings'              => 'كل الحجوزات',
    'Add court'                 => 'إضافة ملعب',
    'Edit court'                => 'تعديل الملعب',
    'List a court'              => 'أضف ملعبك',
    'List a new court'          => 'أضف ملعب جديد',

    // --- home ------------------------------------------------------------
    "Riyadh's sports courts, in one place" => 'ملاعب الرياض، في مكان واحد',
    'Book the court.'           => 'احجز الملعب.',
    'Own the hour.'             => 'والساعة لك.',
    'Wagti is the fastest way to book a sports court near you — football, padel, basketball, volleyball and tennis, all in one place. Compare prices and reserve in seconds.'
        => 'وقتي أسرع طريقة تحجز فيها ملعب قريب منك — كرة قدم، بادل، كرة سلة، طائرة وتنس، كلها في مكان واحد. قارن الأسعار واحجز خلال ثوانٍ.',
    'Find a court'              => 'ابحث عن ملعب',
    'Any sport'                 => 'أي رياضة',
    'Budget per hour'           => 'الميزانية للساعة',
    'Any price'                 => 'أي سعر',
    'Find courts →'             => 'ابحث عن ملعب ←',
    'Confirmed instantly — no phone calls, no waiting for a callback.'
        => 'تأكيد فوري — بدون اتصالات ولا انتظار رد.',
    'At a glance'               => 'نظرة سريعة',
    'Sports'                    => 'رياضات',
    'Riyadh districts'          => 'أحياء في الرياض',
    'Starting from'             => 'تبدأ من',
    'FOOTBALL'                  => 'كرة القدم',
    'PADEL'                     => 'بادل',
    'BASKETBALL'                => 'كرة السلة',
    'VOLLEYBALL'                => 'الكرة الطائرة',
    'TENNIS'                    => 'تنس',
    'BOOK IN SECONDS'           => 'احجز في ثوانٍ',
    'Featured courts'           => 'ملاعب مميزة',

    // --- sports & statuses ----------------------------------------------
    'Football'                  => 'كرة القدم',
    'Padel'                     => 'بادل',
    'Basketball'                => 'كرة السلة',
    'Volleyball'                => 'الكرة الطائرة',
    'Tennis'                    => 'تنس',
    'Available'                 => 'متاح',
    'Maintenance'               => 'صيانة',
    'Under maintenance'         => 'تحت الصيانة',
    'Confirmed'                 => 'مؤكد',
    'Pending'                   => 'بانتظار الدفع',
    'Cancelled'                 => 'ملغي',
    'Listed'                    => 'معروض',
    'Not listed'                => 'غير معروض',
    'Not visible to players'    => 'غير ظاهر للاعبين',

    // --- courts listing & booking ----------------------------------------
    'All courts'                => 'كل الملاعب',
    'All sports'                => 'كل الرياضات',
    'Max price (SAR/hr)'        => 'أعلى سعر (ر.س للساعة)',
    'Filter'                    => 'تصفية',
    'Book'                      => 'احجز',
    'SAR'                       => 'ر.س',
    'SAR/hr'                    => 'ر.س للساعة',
    'No courts match your filter. Try widening the price or choosing another sport.'
        => 'ما فيه ملاعب تطابق البحث. جرّب سعر أعلى أو رياضة ثانية.',
    'Date'                      => 'التاريخ',
    'Start time'                => 'وقت البداية',
    'End time'                  => 'وقت النهاية',
    'Estimated total:'          => 'الإجمالي التقريبي:',
    'Confirm booking'           => 'تأكيد الحجز',
    'Court not found or unavailable.' => 'الملعب غير موجود أو غير متاح.',
    'Back to courts'            => 'رجوع للملاعب',
    'Back'                      => 'رجوع',
    'This time slot is already booked. Please choose another time.'
        => 'هذا الوقت محجوز. اختر وقتًا آخر.',
    'This time slot is already booked.' => 'هذا الوقت محجوز.',
    'This court is under maintenance. Please cancel the booking instead.'
        => 'الملعب تحت الصيانة. الرجاء إلغاء الحجز بدلًا من تعديله.',
    'Booking updated. New total: :total SAR.' => 'تم تعديل الحجز. الإجمالي الجديد: :total ر.س.',
    'Booking not found.'        => 'الحجز غير موجود.',

    // --- slot validation -------------------------------------------------
    'Please choose a valid date.'                     => 'اختر تاريخًا صحيحًا.',
    'Please choose a valid start and end time.'       => 'اختر وقت بداية ونهاية صحيحين.',
    'Bookings start and end on the hour or half hour.' => 'الحجوزات تبدأ وتنتهي على الساعة أو نصف الساعة.',
    'End time must be after start time.'              => 'وقت النهاية لازم يكون بعد وقت البداية.',
    'You cannot book a date in the past.'             => 'ما تقدر تحجز تاريخ فات.',
    'You can only book up to :days days ahead.'       => 'تقدر تحجز حتى :days يوم مقدمًا فقط.',
    'A single booking can be at most :hours hours.'   => 'الحجز الواحد أقصاه :hours ساعات.',

    // --- my bookings -----------------------------------------------------
    'You have no bookings yet.' => 'ما عندك حجوزات للحين.',
    'Browse courts'             => 'تصفح الملاعب',
    'and reserve your first slot.' => 'واحجز أول موعد لك.',
    'Court'                     => 'الملعب',
    'Time'                      => 'الوقت',
    'Total'                     => 'الإجمالي',
    'Status'                    => 'الحالة',
    'Actions'                   => 'إجراءات',
    'Edit'                      => 'تعديل',
    'Cancel'                    => 'إلغاء',
    'Cancel this booking?'      => 'تبي تلغي هذا الحجز؟',
    'Past'                      => 'منتهي',
    'Pay now'                   => 'ادفع الآن',
    'Booking cancelled.'        => 'تم إلغاء الحجز.',
    'That booking could not be cancelled. It may already be cancelled.'
        => 'ما قدرنا نلغي الحجز. يمكن أنه ملغي من قبل.',

    // --- payment ---------------------------------------------------------
    'Test checkout'             => 'دفع تجريبي',
    'Confirm & pay'             => 'أكّد وادفع',
    'Amount due'                => 'المبلغ المستحق',
    'This is a simulated checkout — no real card is charged. In live mode this step is replaced by the secure Moyasar payment page (mada / Visa / Apple Pay).'
        => 'هذا دفع تجريبي — ما راح يُخصم شيء من أي بطاقة. في التشغيل الفعلي تُستبدل هذه الخطوة بصفحة الدفع الآمنة من ميسّر (مدى / فيزا / Apple Pay).',
    'Pay'                       => 'ادفع',
    'Simulate a failed payment' => 'محاكاة دفع فاشل',
    'This booking is already paid.' => 'هذا الحجز مدفوع مسبقًا.',
    'This booking can no longer be paid.' => 'ما عاد يمكن دفع هذا الحجز.',
    'Online payment is temporarily unavailable. Please try again later.'
        => 'الدفع الإلكتروني غير متاح مؤقتًا. حاول مرة ثانية لاحقًا.',
    'Could not start payment right now. Please try again.'
        => 'ما قدرنا نبدأ الدفع الحين. حاول مرة ثانية.',
    'Payment was not completed. You can try again from My Bookings.'
        => 'ما اكتمل الدفع. تقدر تحاول مرة ثانية من حجوزاتي.',
    'Sorry — that slot was taken while your payment was processing. It has been cancelled; you have not kept a confirmed booking.'
        => 'نعتذر — انحجز هذا الوقت أثناء معالجة دفعك. تم إلغاء الحجز ولم يبقَ لك حجز مؤكد.',
    'Payment confirmed — your court is booked. See you on the pitch!'
        => 'تم الدفع — ملعبك محجوز. نشوفك في الملعب!',

    // --- login / register ------------------------------------------------
    'Email'                     => 'البريد الإلكتروني',
    'Password'                  => 'كلمة المرور',
    'New here?'                 => 'جديد هنا؟',
    'Create an account'         => 'أنشئ حساب',
    'Create your account'       => 'أنشئ حسابك',
    'Full name'                 => 'الاسم الكامل',
    'Phone'                     => 'الجوال',
    'Password (min 8 characters)' => 'كلمة المرور (8 أحرف على الأقل)',
    'Create account'            => 'إنشاء الحساب',
    'Already have an account?'  => 'عندك حساب؟',
    'Too many failed attempts. Please wait :minutes minutes and try again.'
        => 'محاولات فاشلة كثيرة. انتظر :minutes دقيقة وحاول مرة ثانية.',
    'Incorrect email or password.' => 'البريد أو كلمة المرور غير صحيحة.',
    'Please enter your full name (2-100 characters).' => 'اكتب اسمك الكامل (من 2 إلى 100 حرف).',
    'Please enter a valid email address.' => 'اكتب بريد إلكتروني صحيح.',
    'Please enter a Saudi mobile number in the form 05XXXXXXXX.' => 'اكتب رقم جوال سعودي بالصيغة 05XXXXXXXX.',
    'Password must be at least 8 characters.' => 'كلمة المرور لازم تكون 8 أحرف على الأقل.',
    'Password is too long.'     => 'كلمة المرور طويلة جدًا.',
    'This email is already registered. Try logging in.' => 'هذا البريد مسجّل من قبل. جرّب تسجيل الدخول.',
    'Your session expired, or this form was submitted from another site. Please go back and try again.'
        => 'انتهت الجلسة، أو أُرسل النموذج من موقع آخر. ارجع وحاول مرة ثانية.',

    // --- owner -----------------------------------------------------------
    'Courts listed'             => 'الملاعب المعروضة',
    'Upcoming bookings'         => 'الحجوزات القادمة',
    'Earned (SAR)'              => 'الأرباح (ر.س)',
    'Your courts'               => 'ملاعبك',
    'Player'                    => 'اللاعب',
    'Court listed — players can book it now.' => 'تمت إضافة الملعب — يقدر اللاعبين يحجزونه الحين.',
    'No courts yet — add your first one to start taking bookings.'
        => 'ما عندك ملاعب للحين — أضف أول ملعب وابدأ تستقبل الحجوزات.',
    'No upcoming bookings yet.' => 'ما فيه حجوزات قادمة للحين.',

    // --- admin -----------------------------------------------------------
    'Customers'                 => 'العملاء',
    'Confirmed bookings'        => 'الحجوزات المؤكدة',
    'Revenue (SAR)'             => 'الإيرادات (ر.س)',
    'Manage courts'             => 'إدارة الملاعب',
    'ID'                        => 'الرقم',
    'Name'                      => 'الاسم',
    'Customer'                  => 'العميل',
    'Sport'                     => 'الرياضة',
    'Location'                  => 'الموقع',
    'Price/hr'                  => 'السعر/ساعة',
    'Price per hour (SAR)'      => 'السعر للساعة (ر.س)',
    'Visible'                   => 'الظهور',
    'Court name'                => 'اسم الملعب',
    'Save changes'              => 'حفظ التعديلات',
    'Back to dashboard'         => 'رجوع للوحة التحكم',
    'Delete'                    => 'حذف',
    'Unlist'                    => 'إخفاء',
    'Publish'                   => 'نشر',
    'Permanently delete this record?' => 'تبي تحذف هذا السجل نهائيًا؟',
    'Hide this court from players? Existing bookings are unaffected.'
        => 'تخفي الملعب عن اللاعبين؟ الحجوزات الحالية ما تتأثر.',
    'Delete this court? Its past bookings will also be removed.'
        => 'تحذف هذا الملعب؟ راح تنحذف حجوزاته السابقة معه.',
    'Court added.'              => 'تمت إضافة الملعب.',
    'Court updated.'            => 'تم تحديث الملعب.',
    'Court deleted.'            => 'تم حذف الملعب.',
    'Court not found.'          => 'الملعب غير موجود.',
    'That court no longer exists.' => 'هذا الملعب لم يعد موجودًا.',
    'That court could not be updated.' => 'ما قدرنا نحدّث الملعب.',
    'Court published — players can find it again.' => 'تم نشر الملعب — اللاعبين يقدرون يشوفونه مرة ثانية.',
    'Court unlisted. Existing bookings are unaffected; it just stops appearing to players.'
        => 'تم إخفاء الملعب. الحجوزات الحالية ما تتأثر؛ بس ما عاد يظهر للاعبين.',
    'This court has :count upcoming booking(s). Cancel them first, or set the court to maintenance instead.'
        => 'لهذا الملعب :count حجز قادم. ألغها أولًا، أو حوّل الملعب إلى صيانة بدلًا من حذفه.',
    'Booking deleted.'          => 'تم حذف الحجز.',
    'That booking no longer exists.' => 'هذا الحجز لم يعد موجودًا.',
    'That booking was not cancelled. It may already be cancelled.'
        => 'ما تم إلغاء الحجز. يمكن أنه ملغي من قبل.',
    'Court name is required (up to 100 characters).' => 'اسم الملعب مطلوب (حتى 100 حرف).',
    'Please choose a sport from the list.' => 'اختر رياضة من القائمة.',
    'Location is required (up to 150 characters).' => 'الموقع مطلوب (حتى 150 حرف).',
    'Price must be a number greater than zero.' => 'السعر لازم يكون رقم أكبر من صفر.',
    'Please choose a valid status.' => 'اختر حالة صحيحة.',
];
