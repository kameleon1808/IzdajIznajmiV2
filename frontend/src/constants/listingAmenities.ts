export const LISTING_AMENITIES = [
  'Basement',
  'Garage',
  'Terrace',
  'Yard',
  'Internet',
  'Cable TV',
  'Phone',
  'Air conditioning',
  'Elevator',
] as const

export type ListingAmenity = (typeof LISTING_AMENITIES)[number]

export const LISTING_AMENITY_LABEL_KEY: Record<
  ListingAmenity,
  | 'amenities.basement'
  | 'amenities.garage'
  | 'amenities.terrace'
  | 'amenities.yard'
  | 'amenities.internet'
  | 'amenities.cable'
  | 'amenities.phone'
  | 'amenities.ac'
  | 'amenities.elevator'
> = {
  Basement: 'amenities.basement',
  Garage: 'amenities.garage',
  Terrace: 'amenities.terrace',
  Yard: 'amenities.yard',
  Internet: 'amenities.internet',
  'Cable TV': 'amenities.cable',
  Phone: 'amenities.phone',
  'Air conditioning': 'amenities.ac',
  Elevator: 'amenities.elevator',
}

const LEGACY_AMENITY_MAP: Record<string, ListingAmenity> = {
  Basement: 'Basement',
  Podrum: 'Basement',
  Garage: 'Garage',
  Garaza: 'Garage',
  'Garaža': 'Garage',
  Parking: 'Garage',
  Terrace: 'Terrace',
  Terasa: 'Terrace',
  Yard: 'Yard',
  Dvoriste: 'Yard',
  'Dvorište': 'Yard',
  Internet: 'Internet',
  'Cable TV': 'Cable TV',
  Cable: 'Cable TV',
  Kablovska: 'Cable TV',
  Phone: 'Phone',
  Telefon: 'Phone',
  'Air conditioning': 'Air conditioning',
  AC: 'Air conditioning',
  Klima: 'Air conditioning',
  Elevator: 'Elevator',
  Lift: 'Elevator',
}

const LEGACY_AMENITY_MAP_LOWER = Object.fromEntries(
  Object.entries(LEGACY_AMENITY_MAP).map(([key, value]) => [key.toLowerCase(), value]),
) as Record<string, ListingAmenity>

export const isListingAmenity = (value: string): value is ListingAmenity => {
  return (LISTING_AMENITIES as readonly string[]).includes(value)
}

export const normalizeListingAmenity = (value: string | null | undefined): ListingAmenity | null => {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  if (!trimmed) return null
  const canonical = LEGACY_AMENITY_MAP[trimmed] ?? LEGACY_AMENITY_MAP_LOWER[trimmed.toLowerCase()] ?? trimmed
  return isListingAmenity(canonical) ? canonical : null
}

export const normalizeListingFacilityValue = (value: string | null | undefined): string | null => {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  if (!trimmed) return null
  return LEGACY_AMENITY_MAP[trimmed] ?? LEGACY_AMENITY_MAP_LOWER[trimmed.toLowerCase()] ?? trimmed
}

export const normalizeListingAmenities = (values: Array<string | null | undefined> | null | undefined): string[] => {
  if (!values?.length) return []

  const seen = new Set<string>()
  const normalizedValues: string[] = []
  for (const value of values) {
    const normalized = normalizeListingFacilityValue(value)
    if (!normalized) continue
    const key = normalized.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    normalizedValues.push(normalized)
  }

  return normalizedValues
}
